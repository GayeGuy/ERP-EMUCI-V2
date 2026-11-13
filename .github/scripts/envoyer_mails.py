#!/usr/bin/env python3
"""Envoie les notifications en attente par mail, depuis GitHub Actions.

Render (offre gratuite) bloque le SMTP sortant : l'application ne peut pas
envoyer elle-même. Ce script lui demande la liste des mails à envoyer
(cron_mails.php?action=lister), les envoie par SMTP depuis le runner GitHub,
puis lui rend le résultat (cron_mails.php?action=resultat).

Variables d'environnement (secrets GitHub) :
  APP_URL, CRON_TOKEN                       liaison avec l'application
  MAIL_HOST, MAIL_PORT, MAIL_SECURE         serveur SMTP (tls = STARTTLS, ssl, none)
  MAIL_USER, MAIL_PASS, MAIL_FROM           identifiants et expéditeur
  MAIL_FROM_NAME                            nom affiché (facultatif)

Ne journalise ni adresse, ni contenu, ni identifiant : seulement des nombres.
"""
import json
import os
import smtplib
import ssl
import sys
import time
import urllib.error
import urllib.request
from email.message import EmailMessage
from email.utils import formataddr


def env(nom, defaut=""):
    return (os.environ.get(nom) or defaut).strip()


def appel(action, corps=None):
    url = env("APP_URL").rstrip("/") + "/cron_mails.php?action=" + action
    donnees = json.dumps(corps).encode() if corps is not None else b""
    derniere = None
    for essai in range(3):
        req = urllib.request.Request(url, data=donnees, method="POST",
                                     headers={"X-Cron-Token": env("CRON_TOKEN"),
                                              "Content-Type": "application/json"})
        try:
            # Le service gratuit s'endort : le premier appel peut durer une minute.
            with urllib.request.urlopen(req, timeout=150) as r:
                return json.loads(r.read().decode())
        except urllib.error.HTTPError as e:
            if e.code < 500:
                try:
                    detail = json.loads(e.read().decode())
                except Exception:
                    detail = {}
                sys.exit("L'application a refusé l'appel (%s) : %s" % (e.code, detail.get("raison", "?")))
            derniere = "HTTP %s" % e.code
        except Exception as e:  # réseau, délai
            derniere = type(e).__name__
        time.sleep(20)
    sys.exit("Application injoignable après 3 essais (%s)." % derniere)


def connexion_smtp():
    hote, port = env("MAIL_HOST"), int(env("MAIL_PORT", "587"))
    secure = env("MAIL_SECURE", "tls").lower()
    ctx = ssl.create_default_context()
    if secure == "ssl":
        s = smtplib.SMTP_SSL(hote, port, timeout=30, context=ctx)
    else:
        s = smtplib.SMTP(hote, port, timeout=30)
        s.ehlo()
        if secure == "tls":
            s.starttls(context=ctx)
            s.ehlo()
    if env("MAIL_USER"):
        s.login(env("MAIL_USER"), os.environ.get("MAIL_PASS", ""))
    return s


def construire(m):
    msg = EmailMessage()
    msg["Subject"] = m["sujet"]
    msg["From"] = formataddr((env("MAIL_FROM_NAME", "ERP EMUCI"), env("MAIL_FROM")))
    msg["To"] = m["to"]
    msg.set_content(m["texte"])
    msg.add_alternative(m["html"], subtype="html")
    return msg


def main():
    for nom in ("APP_URL", "CRON_TOKEN", "MAIL_HOST", "MAIL_FROM"):
        if not env(nom):
            print("Secret %s non défini : rien à faire." % nom)
            return 0
    rep = appel("lister")
    if not rep.get("actif"):
        print("Envoi désactivé côté application (MAIL_ENABLED).")
        return 0
    mails = rep.get("mails", [])
    print("%d mail(s) à envoyer, %d ignoré(s)." % (len(mails), rep.get("ignores", 0)))
    if not mails:
        return 0

    # Panne de connexion ou d'identifiants : on ne compte pas d'échec (rien n'a été
    # refusé par le serveur), le prochain passage réessaie.
    try:
        smtp = connexion_smtp()
    except Exception as e:
        sys.exit("Connexion SMTP impossible : %s %s" % (type(e).__name__, e))

    envoyes, echecs, erreurs = [], [], {}
    for m in mails:
        try:
            smtp.send_message(construire(m))
            envoyes.append(m["id"])
        except smtplib.SMTPServerDisconnected:
            # Connexion coupée en cours de lot : le reste repart au prochain passage.
            break
        except smtplib.SMTPException as e:
            echecs.append(m["id"])
            # Le motif est affiché aux administrateurs (page Mails) ; le journal GitHub n'en garde que le type.
            erreurs[str(m["id"])] = ("%s : %s" % (type(e).__name__, e))[:250]
            print("Refusé par le serveur SMTP : %s" % type(e).__name__)
    try:
        smtp.quit()
    except Exception:
        pass

    bilan = appel("resultat", {"envoyes": envoyes, "echecs": echecs, "erreurs": erreurs})
    print("Envoyés : %d, refusés : %d (enregistrés : %s)." % (len(envoyes), len(echecs), bilan))
    return 1 if echecs and not envoyes else 0


if __name__ == "__main__":
    sys.exit(main())
