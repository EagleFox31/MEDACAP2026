import http from "k6/http";
import { check, sleep } from "k6";
import { SharedArray } from "k6/data";

const BASE_URL = __ENV.BASE_URL || "http://10.68.0.7/medacap";
const PAPER_ID = __ENV.PAPER_ID || "";
const START_PATH = "/views/measure/candidatePaperHome.php";
const QUIZ_PATH = "/views/measure/userPaperQuiz.php";
const LOGIN_PATH = "/views/index.php";

if (!PAPER_ID) {
  throw new Error("Missing PAPER_ID env var. Example: -e PAPER_ID=6a324e65516f0000880007ac");
}

function parseCredentialsFromFile(path) {
  if (!path) return [];
  const raw = open(path);
  if (!raw || !raw.trim()) return [];
  return raw
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [username, password] = line.split(",");
      return {
        username: (username || "").trim(),
        password: (password || "0000").trim(),
      };
    })
    .filter((r) => r.username);
}

function parseCredentialsInline(raw) {
  if (!raw.trim()) return [];
  return raw
    .split(",")
    .map((p) => p.trim())
    .filter(Boolean)
    .map((pair) => {
      const idx = pair.indexOf(":");
      if (idx < 0) {
        return { username: pair, password: "0000" };
      }
      return {
        username: pair.slice(0, idx).trim(),
        password: pair.slice(idx + 1).trim(),
      };
    });
}

// USER_CREDENTIALS format:
// username1:password1,username2:password2,username3:password3
const credentials = new SharedArray("credentials", function () {
  const fromFile = parseCredentialsFromFile(__ENV.USERS_FILE || "");
  if (fromFile.length > 0) return fromFile;

  const raw = __ENV.USER_CREDENTIALS || "";
  const inline = parseCredentialsInline(raw);
  if (inline.length > 0) return inline;

  return [{ username: "demo", password: "0000" }];
});

if (!credentials.length) {
  throw new Error("No credentials loaded. Provide USERS_FILE or USER_CREDENTIALS.");
}

const targetVus = Number(__ENV.TARGET_VUS || 30);
const warmupDuration = __ENV.WARMUP_DURATION || "30s";
const steadyDuration = __ENV.STEADY_DURATION || "2m";
const rampdownDuration = __ENV.RAMPDOWN_DURATION || "30s";
let simulatedRemainingSec = 3600;

export const options = {
  scenarios: {
    candidates_load: {
      executor: "ramping-vus",
      startVUs: 1,
      stages: [
        { duration: warmupDuration, target: targetVus },
        { duration: steadyDuration, target: targetVus },
        { duration: rampdownDuration, target: 0 },
      ],
      gracefulRampDown: "20s",
    },
  },
  thresholds: {
    // Global health
    http_req_failed: ["rate<0.02"],
    http_req_duration: ["p(95)<2000"],
    checks: ["rate>0.98"],

    // Endpoint-level health
    "http_req_failed{step:login}": ["rate<0.02"],
    "http_req_failed{step:candidate_home}": ["rate<0.02"],
    "http_req_failed{step:start_paper}": ["rate<0.02"],
    "http_req_failed{step:open_quiz}": ["rate<0.02"],
    "http_req_failed{step:autosave}": ["rate<0.02"],

    // Endpoint-level latency (p95)
    "http_req_duration{step:login}": ["p(95)<1500"],
    "http_req_duration{step:candidate_home}": ["p(95)<1800"],
    "http_req_duration{step:start_paper}": ["p(95)<2000"],
    "http_req_duration{step:open_quiz}": ["p(95)<2000"],
    "http_req_duration{step:autosave}": ["p(95)<1500"],

    // Functional checks
    "checks{check:login http ok}": ["rate>0.99"],
    "checks{check:candidate home authenticated}": ["rate>0.99"],
    "checks{check:start paper reached quiz}": ["rate>0.99"],
    "checks{check:autosave json ok}": ["rate>0.99"],
  },
};

function pickCred() {
  const i = (__VU - 1) % credentials.length;
  return credentials[i];
}

function login(cred) {
  const payload = {
    username: cred.username,
    password: cred.password,
  };
  const res = http.post(`${BASE_URL}${LOGIN_PATH}`, payload, {
    redirects: 1,
    tags: { step: "login" },
  });
  // Le login peut renvoyer 200 avec redirection/HTML final.
  // La validation d'auth est faite sur la page candidat.
  check(res, {
    "login http ok": (r) => r.status >= 200 && r.status < 400,
  });
}

function openCandidateHome() {
  const res = http.get(`${BASE_URL}${START_PATH}`, {
    tags: { step: "candidate_home" },
  });
  check(res, {
    "candidate home ok": (r) => r.status === 200,
    "candidate home authenticated": (r) =>
      (r.body || "").includes("userPaperQuiz.php?paper=") ||
      (r.body || "").includes('name="paper_id"') ||
      (r.body || "").includes("btn-start"),
  });
  return res;
}

function startPaper() {
  const res = http.post(
    `${BASE_URL}${START_PATH}`,
    {
      start: "1",
      paper_id: PAPER_ID,
    },
    {
      redirects: 1,
      tags: { step: "start_paper" },
    }
  );
  check(res, {
    "start paper 200/302": (r) => r.status === 200 || r.status === 302,
    "start paper reached quiz": (r) =>
      (r.body || "").includes('name="questionsTag[]"') ||
      (r.body || "").includes('id="qHost"'),
  });
}

function openQuiz() {
  const res = http.get(`${BASE_URL}${QUIZ_PATH}?paper=${PAPER_ID}`, {
    tags: { step: "open_quiz" },
  });
  check(res, {
    "open quiz ok": (r) => r.status === 200,
  });
  return res;
}

function secToHms(sec) {
  const s = Math.max(0, Math.floor(sec));
  const hr = Math.floor(s / 3600);
  const mn = Math.floor((s % 3600) / 60);
  const sc = s % 60;
  return {
    hr: String(hr),
    mn: String(mn).padStart(2, "0"),
    sc: String(sc).padStart(2, "0"),
  };
}

function parseQuizMeta(html) {
  const qidSet = new Set();
  const qidRegex = /name="questionsTag\[\]"\s+value="([0-9a-f]{24})"/gi;
  let m;
  while ((m = qidRegex.exec(html)) !== null) {
    qidSet.add(m[1]);
  }

  const optionsByAnswer = {};
  const answerRegex = /name="(answer\d+)"\s+value="([^"]+)"/gi;
  while ((m = answerRegex.exec(html)) !== null) {
    const nm = m[1];
    const val = m[2];
    if (val === "null") continue;
    if (!optionsByAnswer[nm]) optionsByAnswer[nm] = [];
    if (!optionsByAnswer[nm].includes(val)) optionsByAnswer[nm].push(val);
  }

  return {
    qids: Array.from(qidSet),
    optionsByAnswer,
  };
}

function buildAutosaveBody(meta) {
  const tick = 8 + Math.floor(Math.random() * 23);
  simulatedRemainingSec = Math.max(0, simulatedRemainingSec - tick);
  const t = secToHms(simulatedRemainingSec);

  const body = {
    autosave: "1",
    hr: t.hr,
    mn: t.mn,
    sc: t.sc,
  };

  if (meta?.qids?.length) {
    body["questionsTag[]"] = meta.qids;
  }

  if (meta?.optionsByAnswer) {
    Object.keys(meta.optionsByAnswer).forEach((name) => {
      const opts = meta.optionsByAnswer[name] || [];
      if (!opts.length) return;
      const pick = opts[Math.floor(Math.random() * opts.length)];
      body[name] = pick;
    });
  }

  return body;
}

function autosave(meta) {
  const body = buildAutosaveBody(meta);
  const res = http.post(`${BASE_URL}${QUIZ_PATH}?paper=${PAPER_ID}`, body, {
    tags: { step: "autosave" },
  });
  check(res, {
    "autosave ok": (r) => r.status === 200,
    "autosave json ok": (r) => (r.body || "").includes('"ok":true') || (r.body || "").includes('"ok": true'),
  });
}

export default function () {
  const cred = pickCred();

  login(cred);
  sleep(Math.random() * 0.8);

  openCandidateHome();
  sleep(Math.random() * 0.6);

  startPaper();
  sleep(Math.random() * 0.8);

  const quizRes = openQuiz();
  const quizMeta = parseQuizMeta(quizRes.body || "");

  // 3 autosaves par utilisateur virtuel pendant son itération.
  autosave(quizMeta);
  sleep(3 + Math.random() * 2);
  autosave(quizMeta);
  sleep(3 + Math.random() * 2);
  autosave(quizMeta);
}

