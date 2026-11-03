# Captures annotées (zones à cliquer encadrées et numérotées) pour public/guide-connexion.
# Usage : py scripts/guide_connexion_captures.py <dossier des mails de code> [dossier de sortie]
#   Le dossier des mails contient codemail_fr.html et codemail_en.html (mail sendLoginLinkEmail, code 579525).
#   Écrans pris sur le Docker local (127.0.0.1:8084), sortie en JPEG qualité 82.
import io, pathlib, re, sys
from PIL import Image
from playwright.sync_api import sync_playwright

mails = pathlib.Path(sys.argv[1])
out = pathlib.Path(sys.argv[2] if len(sys.argv) > 2 else pathlib.Path(__file__).resolve().parent.parent / 'public' / 'guide-connexion')
out.mkdir(parents=True, exist_ok=True)
base = 'http://127.0.0.1:8084/'

MARK_JS = """([el, n, dashed]) => {
  const r = el.getBoundingClientRect(), pad = 6;
  const box = document.createElement('div');
  box.style.cssText = `position:absolute;z-index:9999;left:${r.left + scrollX - pad}px;top:${r.top + scrollY - pad}px;`
    + `width:${r.width + 2 * pad}px;height:${r.height + 2 * pad}px;border:4px ${dashed ? 'dashed' : 'solid'} #FFC400;`
    + `border-radius:12px;box-sizing:border-box;box-shadow:0 0 0 3px rgba(0,0,0,.35);pointer-events:none;`;
  const b = document.createElement('div');
  b.textContent = n;
  b.style.cssText = (dashed ? 'left:-46px;top:calc(50% - 16px);' : 'left:-18px;top:-18px;')
    + 'position:absolute;width:32px;height:32px;border-radius:50%;background:#FFC400;'
    + 'color:#111;font:700 18px/32px Arial,sans-serif;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.5);';
  box.appendChild(b);
  document.body.appendChild(box);
}"""


def mark(pg, locator, n, dashed=False):
    pg.evaluate(MARK_JS, [locator.element_handle(), str(n), dashed])


def save(pg, name, around):
    pg.wait_for_timeout(500)
    bb = around.bounding_box()
    sx, sy = pg.evaluate('[scrollX, scrollY]')
    png = pg.screenshot(full_page=True, clip={'x': bb['x'] + sx - 28, 'y': bb['y'] + sy - 28, 'width': bb['width'] + 56, 'height': bb['height'] + 56})
    Image.open(io.BytesIO(png)).convert('RGB').save(out / name, 'JPEG', quality=82, optimize=True)


with sync_playwright() as p:
    b = p.chromium.launch()
    for lang in ('fr', 'en'):
        pg = b.new_page(viewport={'width': 1100, 'height': 1000})
        card = pg.locator('.login-card')

        # Chemin A : identifiant + mot de passe
        pg.goto(f'{base}?lang={lang}')
        pg.fill('#username', 'prenom.nom@cfao.com')
        pg.fill('#password', 'motdepasse')
        mark(pg, pg.locator('#username'), 1)
        mark(pg, pg.locator('#password'), 2)
        mark(pg, pg.locator('#submitBtn'), 3)
        save(pg, f'a1_{lang}.jpg', card)

        # Chemin B, écran 1 : bouton « Connectez-vous avec votre mail » (le lien « Mot de passe oublié ? » mène au même écran)
        pg.goto(f'{base}?lang={lang}')
        mark(pg, pg.locator('.magic-alt'), 1)
        save(pg, f'b1_{lang}.jpg', card)

        # Chemin B, écran 2 : adresse mail puis « Recevoir le code »
        pg.goto(f'{base}?lien=1&motif=mail&lang={lang}')
        pg.fill('#magic_email', 'prenom.nom@cfao.com')
        mark(pg, pg.locator('#magic_email'), 2)
        mark(pg, pg.locator('button[name=magic_submit]'), 3)
        save(pg, f'b2_{lang}.jpg', card)

        # Chemin B, écran 4 : saisie du code (page affichée après « Recevoir le code »)
        pg.click('button[name=magic_submit]')
        pg.wait_for_load_state()
        pg.fill('#login_code', '579 525')
        mark(pg, pg.locator('#login_code'), 5)
        mark(pg, pg.locator('button[name=code_submit]'), 6)
        save(pg, f'b4_{lang}.jpg', card)
        pg.close()

        # Chemin B, écran 3 : le mail du code
        pg = b.new_page(viewport={'width': 680, 'height': 1000})
        pg.set_content((mails / f'codemail_{lang}.html').read_text(encoding='utf-8'))
        code = pg.locator('div', has_text=re.compile(r'^\s*579 525\s*$')).last
        mark(pg, code, 4)
        cta = pg.locator('a', has_text='Ouvrir ma session' if lang == 'fr' else 'Open my session').first
        mark(pg, cta, '4b', dashed=True)
        save(pg, f'b3_{lang}.jpg', pg.locator('table').nth(1))
        pg.close()
    b.close()
print(sorted(x.name for x in out.glob('[ab]*.jpg')))
