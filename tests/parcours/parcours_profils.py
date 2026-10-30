"""Parcours utilisateurs par profil, avec captures d'écran.

Se connecte avec un compte par profil, parcourt les liens de menu (2 niveaux),
prend une capture par page et signale les erreurs PHP / HTTP.

    python tests/parcours/parcours_profils.py --accounts comptes.json
    python tests/parcours/parcours_profils.py --accounts comptes.json --base http://127.0.0.1:8084 --max 40

comptes.json (jamais versionné) :
    {"Technicien": {"username": "...", "password": "..."}, "Manager": {...}}

Sorties : docs/captures/<profil>/NN-page.jpg et docs/captures/rapport.json
"""
import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path
from urllib.parse import urljoin, urlparse

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs" / "captures"

# Liens jamais suivis : actions, envois, exports, déconnexion
SKIP = re.compile(
    r"logout|deconnex|delete|supprim|remove|reset|send|mail|export|download|"
    r"cron|setup|/scripts/|action=|desactiv|activat|archive|import|magic|"
    # ouvrir un QCM crée ou modifie une épreuve : exclu pour rester sans effet sur la base
    r"quiz|congrat|startPaper|/api/|"
    # le portail pointe volontairement vers /404 pour les étapes non autorisées
    r"/404(\.php)?(\?|$)|"
    r"\.(pdf|xlsx?|csv|zip|png|jpe?g|svg|json)(\?|$)",
    re.I,
)
# Bloquant : la page casse. Le reste (Warning, Notice, Deprecated) est noté à part.
PHP_FATAL = re.compile(r"(Fatal error|Parse error|Uncaught)")
PHP_ERR = re.compile(
    r"(Fatal error|Parse error|Uncaught|Stack trace|Warning</b>:|Notice</b>:|"
    r"Deprecated</b>:|Warning: |Notice: |Deprecated: )"
)


def slug(text):
    text = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode()
    text = re.sub(r"[^A-Za-z0-9]+", "-", text).strip("-")
    return text[:60] or "page"


def collect_links(page, base_host):
    hrefs = page.eval_on_selector_all("a[href]", "els => els.map(e => e.href)")
    links = []
    for h in hrefs:
        u = urlparse(h)
        if u.netloc != base_host or SKIP.search(h):
            continue
        if u.path.endswith("/") and u.path.count("/") <= 1:
            continue
        links.append(h.split("#")[0])
    return links


def run_profile(browser, base, profile, creds, max_pages, skip=None):
    folder = OUT / slug(profile)
    folder.mkdir(parents=True, exist_ok=True)
    for old in folder.glob("*.jpg"):
        old.unlink()
    ctx = browser.new_context(viewport={"width": 1440, "height": 900}, locale="fr-FR")
    page = ctx.new_page()
    # Sans délai par défaut, une page figée bloque tout le parcours ; un alert() aussi
    page.set_default_timeout(60000)
    page.on("dialog", lambda d: d.dismiss())
    report ={"profil": profile, "pages": [], "erreurs": 0, "avertissements": 0}
    base_host = urlparse(base).netloc

    page.goto(base + "/", wait_until="domcontentloaded")
    page.fill("#username", creds["username"])
    page.fill("#password", creds["password"])
    # La page d'arrivée (tableau de bord) peut être lente : on attend seulement la réponse
    page.locator("form").filter(has=page.locator("#password")).locator("[type=submit]").first.click(no_wait_after=True)
    try:
        page.wait_for_load_state("domcontentloaded", timeout=120000)
        page.wait_for_function("!document.querySelector('#password')", timeout=120000)
    except Exception:
        pass
    if page.locator("#password").count():
        report["connexion"] = "échec"
        report["erreurs"] += 1
        ctx.close()
        return report
    report["connexion"] = "ok"

    queue = [(page.url, 0, "connexion")]
    seen = set()
    n = 0
    while queue and n < max_pages:
        url, depth, source = queue.pop(0)
        # /x et /x.php servent la même page
        key = re.sub(r"\.php$", "", urlparse(url).path) + "?" + urlparse(url).query
        if key in seen:
            continue
        seen.add(key)
        if skip and re.search(skip, url):
            report["pages"].append({"url": url.replace(base, ""), "statut": "exclue (--skip)"})
            continue
        try:
            resp = page.goto(url, wait_until="domcontentloaded", timeout=120000)
            try:
                page.wait_for_load_state("networkidle", timeout=5000)
            except Exception:
                pass
            status = resp.status if resp else 0
            html = page.content()
        except Exception as exc:
            report["pages"].append({"url": url, "statut": "exception", "detail": str(exc)[:200]})
            report["erreurs"] += 1
            continue
        if page.locator("#password").count() and "index" in page.url:
            report["pages"].append({"url": url, "statut": "redirigé vers la connexion"})
            continue
        m = PHP_ERR.search(html)
        not_found = re.search(r"/404(\.php)?$", urlparse(page.url).path) is not None
        fatal = bool(m and PHP_FATAL.search(html))
        ok = status < 400 and not fatal and not not_found
        name = "%02d-%s.jpg" % (n, slug(urlparse(page.url).path.split("/")[-1].replace(".php", "") + "-" + urlparse(page.url).query))
        page.screenshot(path=str(folder / name), type="jpeg", quality=60)
        entry = {"url": page.url.replace(base, ""), "statut": status, "capture": name,
                 "titre": page.title(), "depuis": source.replace(base, "")}
        if not_found:
            entry["erreur"] = "lien cassé : " + url.replace(base, "")
        if m:
            i = m.start()
            entry["erreur_php"] = re.sub(r"<[^>]+>", "", html[i:i + 300]).strip()
        if not ok:
            report["erreurs"] += 1
        elif m:
            report["avertissements"] += 1
        report["pages"].append(entry)
        n += 1
        if depth < 2:
            for link in collect_links(page, base_host):
                queue.append((link, depth + 1, page.url))
    ctx.close()
    return report


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--accounts", required=True)
    ap.add_argument("--base", default="http://127.0.0.1:8084")
    ap.add_argument("--max", type=int, default=40)
    ap.add_argument("--only", help="profils à passer, séparés par des virgules")
    ap.add_argument("--skip", help="regex d'URL à ne pas ouvrir (pages trop lourdes pour le navigateur)")
    args = ap.parse_args()
    accounts = json.loads(Path(args.accounts).read_text(encoding="utf-8"))
    results = []
    with sync_playwright() as p:
        browser = None
        # Chromium de Playwright s'il est installé, sinon Chrome ou Edge du poste
        for channel in (None, "chrome", "msedge"):
            try:
                browser = p.chromium.launch(channel=channel) if channel else p.chromium.launch()
                break
            except Exception:
                continue
        if browser is None:
            sys.exit("Aucun navigateur : lancer `python -m playwright install chromium`")
        for profile, creds in accounts.items():
            if args.only and profile not in args.only.split(","):
                continue
            try:
                r = run_profile(browser, args.base.rstrip("/"), profile, creds, args.max, args.skip)
            except Exception as exc:
                r = {"profil": profile, "connexion": "exception", "pages": [], "erreurs": 1, "detail": str(exc)[:300]}
                (OUT / slug(profile)).mkdir(parents=True, exist_ok=True)
            print("%-28s connexion=%s pages=%d erreurs=%d avertissements=%d" % (profile, r["connexion"], len(r["pages"]), r["erreurs"], r.get("avertissements", 0)), flush=True)
            (OUT / slug(profile) / "rapport.json").write_text(json.dumps(r, ensure_ascii=False, indent=2), encoding="utf-8")
            results.append(r)
        browser.close()
    # Synthèse de tous les profils déjà passés (relançable profil par profil)
    allr = [json.loads(f.read_text(encoding="utf-8")) for f in sorted(OUT.glob("*/rapport.json"))]
    (OUT / "rapport.json").write_text(json.dumps(allr, ensure_ascii=False, indent=2), encoding="utf-8")
    sys.exit(1 if any(r["erreurs"] for r in results) else 0)


if __name__ == "__main__":
    main()
