<?php
/**
 * Candidats — Technicien 2025 (Refactor POO + Patterns) — PHP 7.3
 */
session_start();
include_once "../language.php";
include_once "../partials/background-manager.php";

if (!isset($_SESSION["id"])) { header("Location: ../../"); exit(); }

require_once "../../vendor/autoload.php";

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;


/* ============================================================
|  Config
|============================================================ */
final class AppConfig {
    public $mongoDsn = "mongodb://localhost:27017";
    public $dbName   = "academy";
    public $qidLoc  = "68a8ba9939846c0f8fe86262"; // Lieu
    public $qidWork = "68a8ba9939846c0f8fe86261"; // Atelier

    public $statusOrder = ['started','awaiting','done'];
    public $statusLabels = [
        'started'=>'Démarré',
        'awaiting'=>'Orientation en attente',
        'done'=>'Terminé',
        'invited'=>'Invité',
    ];
}

/* ============================================================
|  Request (entrée HTTP)
|============================================================ */
final class RequestParams {
    public $isExport;
    public $country;
    public $agency;
    public $specialty;
    public $status;
    public $oloc;
    public $owork;
    public $paperId;
    public $paperOid;
    public $dateFilter;
    public $campaign;
    public $oriFilters; // ['questionId' => 'valeur sélectionnée']
    public $baseParams;

    public static function fromGlobals() {
        $r = new self();
        $r->isExport   = (isset($_GET['export']) && $_GET['export'] === '1');
        $r->country    = isset($_GET['country'])   ? trim($_GET['country'])   : null;
        $r->agency     = isset($_GET['agency'])    ? trim($_GET['agency'])    : null;
        $r->specialty  = isset($_GET['specialty']) ? trim($_GET['specialty']) : null;
        $r->status     = isset($_GET['status'])    ? trim($_GET['status'])    : null;
        $r->oloc       = isset($_GET['oloc'])      ? trim($_GET['oloc'])      : null;
        $r->owork      = isset($_GET['owork'])     ? trim($_GET['owork'])     : null;
        $r->paperId    = isset($_GET['paper'])     ? trim($_GET['paper'])     : null;
        $r->paperOid   = ($r->paperId && preg_match('/^[0-9a-f]{24}$/i', $r->paperId)) ? new ObjectId($r->paperId) : null;
        $r->dateFilter = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($_GET['date']))
            ? trim($_GET['date']) : null;
        $r->campaign   = isset($_GET['campaign'])  ? trim($_GET['campaign'])  : null;
        // Filtres orientation dynamiques : ?ori[qid]=valeur
        $r->oriFilters = [];
        if (isset($_GET['ori']) && is_array($_GET['ori'])) {
            foreach ($_GET['ori'] as $qid => $val) {
                $qid = trim((string)$qid);
                $val = trim((string)$val);
                if ($qid !== '' && $val !== '') $r->oriFilters[$qid] = $val;
            }
        }
        $r->baseParams = $_GET;
        return $r;
    }
}

/* ============================================================
|  Utils
|============================================================ */
final class UrlBuilder {
    public static function build($baseParams, $overrides = []) {
        $p = array_merge($baseParams, $overrides);
        foreach ($p as $k=>$v) if ($v===null || $v==='') unset($p[$k]);
        $qs = http_build_query($p);
        return $qs ? ('?'.$qs) : '?';
    }
}

final class Arrays {
    public static function strJoin($arr, $sep=' / ') {
        return implode($sep, array_map('strval', (array)$arr));
    }
}

/* ============================================================
|  Factory (Mongo)
|============================================================ */
final class MongoFactory {
    private $client;
    private $db;

    public function __construct(AppConfig $cfg) {
        $this->client = new Client($cfg->mongoDsn);
        $this->db     = $this->client->{$cfg->dbName};
    }
    public function col($name) { return $this->db->{$name}; }
}

/* ============================================================
|  Query Builder (SRP: construit pipeline candidats)
|============================================================ */
final class CandidateQueryBuilder {
    private $cfg; private $req; private $paperOriQuestions;
    public function __construct(AppConfig $cfg, RequestParams $req, $paperOriQuestions = []) {
        $this->cfg = $cfg;
        $this->req = $req;
        $this->paperOriQuestions = $paperOriQuestions;
    }

    public function baseMatch() {
        $m = [
            'active'  => true,
            'profile' => 'Candidat',
        ];

        // ─── Filtre Campagne de recrutement ───────────────────────
        // Si une campagne est sélectionnée on filtre sur le champ role
        // (ou roles pour les comptes multi-rôles).
        // Sinon on récupère tous les Candidats.
        if ($this->req->campaign) {
            $m['$or'] = [
                ['role'  => $this->req->campaign],
                ['roles' => ['$in' => [$this->req->campaign]]],
            ];
        }
        // ──────────────────────────────────────────────────────────

        if ($this->req->country)   $m['country']   = $this->req->country;
        if ($this->req->agency)    $m['agency']     = $this->req->agency;
        if ($this->req->specialty) $m['specialty']  = $this->req->specialty;

        return $m;
    }

    public function nameNormalization() {
        return [
            ['$addFields'=>[
                'fname'=>['$ifNull'=>['$firstName','']],
                'lname'=>['$ifNull'=>['$lastName','']],
                'nameFallback'=>['$ifNull'=>['$username','—']],
            ]],
            ['$addFields'=>[
                'fullnameRaw'=>['$trim'=>['input'=>['$concat'=>[['$ifNull'=>['$fname','']],' ',['$ifNull'=>['$lname','']]]]]]
            ]],
            ['$addFields'=>[
                'fullname'=>['$cond'=>[['$ne'=>['$fullnameRaw','']], '$fullnameRaw', '$nameFallback']]
            ]],
        ];
    }

    /** pipeline principal */
    public function build() {
        $paperMatch = $this->req->paperOid ? [['$match'=>['paper'=>$this->req->paperOid]]] : [];

        // ─── Date filter ──────────────────────────────────────────
        // Filtre appliqué DANS le sous-pipeline du lookup resFact
        // + un $match post-lookup pour ne garder que les candidats
        // ayant une composition ce jour-là.
        $dateMatchInner = [];
        $dateMatchOuter = [];
        if ($this->req->dateFilter) {
            $dateStart = $this->req->dateFilter . ' 00:00:00';
            $dateEnd   = $this->req->dateFilter . ' 23:59:59';
            $dateMatchInner = [['$match'=>[
                'finishedAt'=>[
                    '$gte' => $dateStart,
                    '$lte' => $dateEnd,
                ]
            ]]];
            // Après le lookup on ne garde que ceux qui ont un résultat sur la date
            $dateMatchOuter = [['$match'=>['resFact'=>['$ne',[]]]]];
        }
        // ──────────────────────────────────────────────────────────

        $pipeline = array_merge(
            [
                ['$match'=>$this->baseMatch()],
            ],
            $this->nameNormalization(),
            [
                // Dernier QCM factuel Mixed  — filtre date injecté ICI ↓
                ['$lookup'=>[
                    'from'=>'results',
                    'let'=>['uid'=>'$_id'],
                    'pipeline'=>array_values(array_filter(array_merge([
                        ['$match'=>[
                            '$expr'=>[
                                '$and'=>[
                                    ['$eq'=>['$user','$$uid']],
                                    ['$eq'=>['$type','Factuel']],
                                    ['$eq'=>['$level','Mixed']],
                                ]
                            ],
                            'userAnswers'=>['$exists'=>true]
                        ]],
                    ], $paperMatch, $dateMatchInner, [   // ← dateMatchInner ajouté ici
                        ['$sort'=>['created'=>-1]],
                        ['$limit'=>1],
                        ['$project'=>['score'=>1,'total'=>1,'finishedAt'=>1]],
                    ]))),
                    'as'=>'resFact'
                ]],
            ],
            // Filtre post-lookup si date active
            $dateMatchOuter,
            [
                // Dernière allocation
                ['$lookup'=>[
                    'from'=>'allocations',
                    'let'=>['uid'=>'$_id'],
                    'pipeline'=>array_values(array_filter(array_merge([
                        ['$match'=>['$expr'=>['$eq'=>['$user','$$uid']]]],
                    ], $paperMatch, [
                        ['$sort'=>['created'=>-1]],
                        ['$limit'=>1],
                        ['$project'=>['status'=>1]],
                    ]))),
                    'as'=>'alloc'
                ]],

                // Orientation (dernier doc)
                ['$lookup'=>[
                    'from'=>'results',
                    'let'=>['uid'=>'$_id'],
                    'pipeline'=>array_values(array_filter(array_merge([
                        ['$match'=>[
                            '$expr'=>[
                                '$and'=>[
                                    ['$eq'=>['$user','$$uid']],
                                    ['$eq'=>['$type','Orientation']],
                                    ['$eq'=>['$level','Mixed']],
                                ]
                            ]
                        ]],
                    ], $paperMatch, [
                        ['$sort'=>['created'=>-1]],
                        ['$limit'=>1],
                    ]))),
                    'as'=>'resOri'
                ]],

                // Tous les résultats factuels groupés par paper (pour la modal)
                ['$lookup'=>[
                    'from'=>'results',
                    'let'=>['uid'=>'$_id'],
                    'pipeline'=>[
                        ['$match'=>[
                            '$expr'=>[
                                '$and'=>[
                                    ['$eq'=>['$user','$$uid']],
                                    ['$eq'=>['$type','Factuel']],
                                    ['$eq'=>['$level','Mixed']],
                                ]
                            ],
                            'userAnswers'=>['$exists'=>true]
                        ]],
                        ['$sort'=>['created'=>-1]],
                        ['$group'=>[
                            '_id'=>['$ifNull'=>['$paper', null]],
                            'score'=>['$first'=>'$score'],
                            'total'=>['$first'=>'$total'],
                        ]],
                    ],
                    'as'=>'allPaperResults'
                ]],

                // Filiale -> drapeau
                ['$lookup'=>[
                    'from'=>'cfao_subsidiaries',
                    'localField'=>'subsidiary',
                    'foreignField'=>'subsidiary',
                    'as'=>'subinfo'
                ]],
                ['$addFields'=>[
                    'sub_country'      =>['$ifNull'=>[['$arrayElemAt'=>['$subinfo.country',0]], '$country']],
                    'flagFromComplete' =>['$arrayElemAt'=>['$subinfo.flagPathComplete',0]],
                    'flagFromShort'    =>['$arrayElemAt'=>['$subinfo.flagPath',0]],
                ]],
                ['$addFields'=>[
                    'flagPath'=>[
                        '$cond'=>[
                            ['$and'=>[
                                ['$ne'=>['$flagFromComplete',null]],
                                ['$ne'=>['$flagFromComplete','']],
                            ]],
                            '$flagFromComplete',
                            [
                                '$cond'=>[
                                    ['$and'=>[
                                        ['$ne'=>['$flagFromShort',null]],
                                        ['$ne'=>['$flagFromShort','']],
                                    ]],
                                    ['$concat'=>['$flagFromShort','.svg']],
                                    null
                                ]
                            ]
                        ]
                    ]
                ]],

                // KPIs + Orientation extraction
                ['$addFields'=>[
                    'sumScore'=>['$reduce'=>['input'=>'$resFact','initialValue'=>0,'in'=>['$add'=>['$$value',['$ifNull'=>['$$this.score',0]]]]]],
                    'sumTotal'=>['$reduce'=>['input'=>'$resFact','initialValue'=>0,'in'=>['$add'=>['$$value',['$ifNull'=>['$$this.total',0]]]]]],
                    'attempted'=>['$gt'=>[[ '$size' => [ '$ifNull' => ['$resFact', []] ] ],0]],
                    'hasAlloc'    =>['$gt'=>[[ '$size' => [ '$ifNull' => ['$alloc',   []] ] ],0]],
                    'allocStatus' =>['$ifNull'=>[['$arrayElemAt'=>['$alloc.status',0]], '']],
                    'oriFirst'    =>['$arrayElemAt'=>['$resOri',0]],
                ]],

                ['$addFields'=>[
                    'oriAnsArr' => [
                        '$ifNull'=>[
                            '$oriFirst.answers',
                            ['$ifNull'=>['$oriFirst.userAnswers', []]]
                        ]
                    ],
                ]],
                ['$addFields'=>[
                    'oriLocations' => [
                        '$let'=>[
                            'vars'=>[
                                'hit'=>[
                                    '$arrayElemAt'=>[[
                                        '$filter'=>[
                                            'input'=>'$oriAnsArr','as'=>'a',
                                            'cond'=>['$eq'=>['$$a.id', $this->cfg->qidLoc]]
                                        ]
                                    ],0]
                                ]
                            ],
                            'in'=>[
                                '$map'=>[
                                    'input'=>['$ifNull'=>['$$hit.selected', []]],
                                    'as'=>'v',
                                    'in'=>['$trim'=>['input'=>'$$v']]
                                ]
                            ]
                        ]
                    ],
                    'oriWorkshops' => [
                        '$let'=>[
                            'vars'=>[
                                'hit'=>[
                                    '$arrayElemAt'=>[[
                                        '$filter'=>[
                                            'input'=>'$oriAnsArr','as'=>'a',
                                            'cond'=>['$eq'=>['$$a.id', $this->cfg->qidWork]]
                                        ]
                                    ],0]
                                ]
                            ],
                            'in'=>[
                                '$map'=>[
                                    'input'=>['$ifNull'=>['$$hit.selected', []]],
                                    'as'=>'v',
                                    'in'=>['$trim'=>['input'=>'$$v']]
                                ]
                            ]
                        ]
                    ],
                    'oriDone' => [
                        '$gt' => [
                            [ '$size' => [ '$ifNull' => ['$resOri', []] ] ],
                            0
                        ]
                    ],
                ]],

                ['$addFields'=>[
                    'pct'=>[
                        '$cond'=>[
                            [ '$gt'=>['$sumTotal',0] ],
                            [ '$multiply'=>[[ '$divide'=>['$sumScore','$sumTotal'] ], 100] ],
                            0
                        ]
                    ],
                    'qcmComplete' => [ '$gt' => [ [ '$size' => [ '$ifNull' => ['$resFact', []] ] ], 0 ] ],
                ]],
                ['$addFields'=>[
                    'started'  => [ '$or' => [ '$hasAlloc', '$qcmComplete', '$oriDone' ] ],
                    'done'     => [ '$or' => [ [ '$eq' => ['$allocStatus','done'] ], '$oriDone' ] ],
                    'awaiting' => [ '$or' => [
                        [ '$eq' => ['$allocStatus','awaiting_orientation'] ],
                        [ '$and' => [ '$qcmComplete', [ '$not' => [ '$oriDone' ] ] ] ]
                    ] ],
                ]],

                ['$addFields'=>[
                    'statusKey'=>[
                        '$switch'=>[
                            'branches'=>[
                                ['case'=>'$done','then'=>'done'],
                                ['case'=>'$awaiting','then'=>'awaiting'],
                                ['case'=>'$started','then'=>'started'],
                            ],
                            'default'=>'invited'
                        ]
                    ]
                ]],

            ]
        );

        // ─── Champs orientation dynamiques (selon paper sélectionné) ───
        if (!empty($this->paperOriQuestions)) {
            $dynFields = [];
            foreach ($this->paperOriQuestions as $qid => $q) {
                $fieldName = 'oriQ_' . $qid;
                $dynFields[$fieldName] = [
                    '$let' => [
                        'vars' => [
                            'hit' => ['$arrayElemAt' => [[
                                '$filter' => [
                                    'input' => '$oriAnsArr',
                                    'as'    => 'a',
                                    'cond'  => ['$eq' => ['$$a.id', $qid]],
                                ]
                            ], 0]]
                        ],
                        'in' => ['$map' => [
                            'input' => ['$ifNull' => ['$$hit.selected', []]],
                            'as'    => 'v',
                            'in'    => ['$trim' => ['input' => '$$v']],
                        ]]
                    ]
                ];
            }
            $pipeline[] = ['$addFields' => $dynFields];
        }
        // ───────────────────────────────────────────────────────────────

        // ─── Sorties ───────────────────────────────────────────────────
        $project = [
            '_id'      => 0,
            'fullname' => 1,
            'country'  => ['$ifNull' => ['$sub_country', ['$ifNull' => ['$country', '']]]],
            'flagPath' => 1,
            'agency'   => ['$ifNull' => ['$agency', '']],
            'attempted'=> 1,
            'pct'      => ['$round' => ['$pct', 1]],
            'statusKey'=> 1,
            'gender'   => 1,
            'oriLocations' => 1,
            'oriWorkshops' => 1,
            'sumScore' => 1, 'sumTotal' => 1,
            'role'     => 1,
            'userId'          => ['$toString' => '$_id'],
            'allPaperResults' => 1,
            'oriAnsArr'       => 1,
        ];
        // Inclure les champs dynamiques dans la projection
        foreach ($this->paperOriQuestions as $qid => $q) {
            $project['oriQ_' . $qid] = 1;
        }
        $pipeline[] = ['$project' => $project];
        $pipeline[] = ['$limit' => 2000];

        return $pipeline;
    }
}

/* ============================================================
|  Repositories
|============================================================ */
final class CandidateRepository {
    private $usersCol; private $qb;
    public function __construct($usersCol, CandidateQueryBuilder $qb) { $this->usersCol=$usersCol; $this->qb=$qb; }
    public function fetchAll() {
        return $this->usersCol->aggregate($this->qb->build())->toArray();
    }
}

final class OrientationOptionsRepository {
    private $col; private $cfg;
    public function __construct($oriQCol, AppConfig $cfg) { $this->col=$oriQCol; $this->cfg=$cfg; }
    public function getDistinctFromQuestions($fallbackCandidates) {
        $distinctLoc=[]; $distinctWork=[];
        try {
            $idLoc  = new ObjectId($this->cfg->qidLoc);
            $idWork = new ObjectId($this->cfg->qidWork);
            $docLoc  = $this->col->findOne(['$or'=>[['_id'=>$idLoc], ['id'=>$this->cfg->qidLoc]]],  ['projection'=>['options'=>1]]);
            $docWork = $this->col->findOne(['$or'=>[['_id'=>$idWork],['id'=>$this->cfg->qidWork]]], ['projection'=>['options'=>1]]);
            if ($docLoc && isset($docLoc['options']) && is_iterable($docLoc['options'])) {
                foreach ($docLoc['options'] as $o) { $v = trim((string)$o); if ($v!=='') $distinctLoc[$v]=true; }
            }
            if ($docWork && isset($docWork['options']) && is_iterable($docWork['options'])) {
                foreach ($docWork['options'] as $o) { $v = trim((string)$o); if ($v!=='') $distinctWork[$v]=true; }
            }
        } catch (Throwable $e) { /* fallback below */ }

        if (!$distinctLoc || !$distinctWork) {
            foreach ($fallbackCandidates as $c) {
                foreach ((array)($c['oriLocations'] ?? []) as $vv){ $vv=trim((string)$vv); if($vv!=='') $distinctLoc[$vv]=true; }
                foreach ((array)($c['oriWorkshops'] ?? []) as $vv){ $vv=trim((string)$vv); if($vv!=='') $distinctWork[$vv]=true; }
            }
        }
        $loc  = array_keys($distinctLoc);  sort($loc, SORT_NATURAL|SORT_FLAG_CASE);
        $work = array_keys($distinctWork); sort($work,SORT_NATURAL|SORT_FLAG_CASE);
        return [$loc,$work];
    }
}

/**
 * ─── NOUVEAU : récupère les campagnes de recrutement distinctes ───────────
 * Lit les valeurs distinctes du champ `role` dans la collection `users`
 * en ne ciblant que les documents avec profile = 'Candidat'.
 * Fusionne également les entrées du tableau `roles` (multi-rôles).
 */
final class CampaignRepository {
    private $usersCol;
    public function __construct($usersCol) { $this->usersCol = $usersCol; }

    public function getDistinct() {
        $campaigns = [];
        try {
            $pipeline = [
                ['$match' => ['profile' => 'Candidat', 'active' => true]],
                ['$project' => [
                    'allRoles' => [
                        '$setUnion' => [
                            ['$cond' => [
                                ['$and' => [
                                    ['$ne' => ['$role', null]],
                                    ['$ne' => ['$role', '']],
                                ]],
                                [['$ifNull' => ['$role', '']]],
                                []
                            ]],
                            ['$ifNull' => ['$roles', []]],
                        ]
                    ]
                ]],
                ['$unwind' => '$allRoles'],
                ['$match'  => ['allRoles' => ['$ne' => '']]],
                ['$group'  => ['_id' => '$allRoles']],
                ['$sort'   => ['_id' => 1]],
            ];
            $rows = $this->usersCol->aggregate($pipeline)->toArray();
            foreach ($rows as $r) {
                $v = trim((string)($r['_id'] ?? ''));
                if ($v !== '') $campaigns[] = $v;
            }
        } catch (Throwable $e) { /* retourne tableau vide en cas d'erreur */ }

        return $campaigns;
    }
}
// ─────────────────────────────────────────────────────────────────────────────

/* ============================================================
|  Paper Orientation Loader
|  Charge les questions d'orientation d'un paper donné
|============================================================ */
final class PaperOrientationLoader {
    private $papersCol;
    private $oriQCol;

    public function __construct($papersCol, $oriQCol) {
        $this->papersCol = $papersCol;
        $this->oriQCol   = $oriQCol;
    }

    /** Retourne ['questionId' => ['id'=>..., 'label'=>..., 'options'=>[...]]] */
    public function load($paperOid) {
        if (!$paperOid) return [];
        $paper = $this->papersCol->findOne(['_id' => $paperOid], ['projection' => ['orientation' => 1]]);
        if (!$paper || empty($paper['orientation'])) return [];

        $oriIds = [];
        foreach ($paper['orientation'] as $o) {
            $oid = ($o instanceof ObjectId) ? $o : null;
            if ($oid) $oriIds[] = $oid;
        }
        if (empty($oriIds)) return [];

        // Fetch questions
        $byId = [];
        $cursor = $this->oriQCol->find(['_id' => ['$in' => $oriIds]]);
        foreach ($cursor as $q) {
            $qid  = (string)$q['_id'];
            $opts = [];
            if (isset($q['options']) && is_iterable($q['options'])) {
                foreach ($q['options'] as $o) { $opts[] = (string)$o; }
            }
            $byId[$qid] = [
                'id'      => $qid,
                'label'   => (string)($q['label'] ?? $q['text'] ?? 'Question'),
                'options' => $opts,
            ];
        }

        // Respecter l'ordre du paper
        $ordered = [];
        foreach ($oriIds as $oid) {
            $qid = (string)$oid;
            if (isset($byId[$qid])) $ordered[$qid] = $byId[$qid];
        }
        return $ordered;
    }
}
// ─────────────────────────────────────────────────────────────────────────────

/* ============================================================
|  Services (tri, export…)
|============================================================ */
final class CandidateService {
    private $cfg;
    public function __construct(AppConfig $cfg) { $this->cfg=$cfg; }

    public function filterByStatus($cands, $status) {
        if ($status && in_array($status, $this->cfg->statusOrder, true)) {
            $out=[]; foreach ($cands as $c) if (($c['statusKey']??'invited')===$status) $out[]=$c; return $out;
        }
        return $cands;
    }

    public function filterByOrientation($cands, $loc, $work) {
        if (!$loc && !$work) return $cands;
        return array_values(array_filter($cands, function($c) use ($loc, $work) {
            $locs = (array)($c['oriLocations'] ?? []);
            $wrks = (array)($c['oriWorkshops'] ?? []);
            if ($loc  && !in_array($loc,  $locs, true)) return false;
            if ($work && !in_array($work, $wrks, true)) return false;
            return true;
        }));
    }

    /** Filtrage orientation dynamique selon les questions du paper sélectionné */
    public function filterByOrientationDynamic($cands, $oriFilters) {
        if (empty($oriFilters)) return $cands;
        return array_values(array_filter($cands, function($c) use ($oriFilters) {
            foreach ($oriFilters as $qid => $val) {
                $answers = (array)($c['oriQ_' . $qid] ?? []);
                if (!in_array($val, $answers, true)) return false;
            }
            return true;
        }));
    }

    public function splitAttempted($cands) {
        $attempted=[]; $notAttempted=[];
        foreach ($cands as $c) { if (!empty($c['attempted'])) $attempted[]=$c; else $notAttempted[]=$c; }
        return [$attempted,$notAttempted];
    }

    public function sortByPctDescThenName(&$attempted) {
        usort($attempted, function($a,$b){
            $pa = (float)($a['pct'] ?? 0); $pb = (float)($b['pct'] ?? 0);
            if ($pa === $pb) return strcasecmp((string)$a['fullname'], (string)$b['fullname']);
            return ($pa > $pb) ? -1 : 1;
        });
    }

    public function statusCounts($cands) {
        $counts = array_fill_keys($this->cfg->statusOrder, 0);
        foreach ($cands as $c) { $sk = $c['statusKey'] ?? 'invited'; if (isset($counts[$sk])) $counts[$sk]++; }
        return $counts;
    }
}

final class CsvExporter {
    private $cfg;
    public function __construct(AppConfig $cfg){ $this->cfg=$cfg; }
    public function export($leaderboard,$others,$notAttempted) {
        $rows=[]; $rank=1;
        $join = function($arr){ return implode(' / ', array_map('strval',(array)$arr)); };

        foreach ($leaderboard as $p) {
            $rows[] = [
                $rank++,
                (string)($p['fullname'] ?? '—'),
                (string)($p['country'] ?? ''),
                $this->cfg->statusLabels[$p['statusKey'] ?? 'invited'] ?? '—',
                (string)number_format((float)($p['pct'] ?? 0),1,'.',''),
                (string)((int)($p['sumScore'] ?? 0)),
                (string)((int)($p['sumTotal'] ?? 0)),
                $join($p['oriLocations'] ?? []),
                $join($p['oriWorkshops'] ?? []),
            ];
        }
        foreach ($others as $p) {
            $rows[] = [
                $rank++,
                (string)($p['fullname'] ?? '—'),
                (string)($p['country'] ?? ''),
                $this->cfg->statusLabels[$p['statusKey'] ?? 'invited'] ?? '—',
                (string)number_format((float)($p['pct'] ?? 0),1,'.',''),
                (string)((int)($p['sumScore'] ?? 0)),
                (string)((int)($p['sumTotal'] ?? 0)),
                $join($p['oriLocations'] ?? []),
                $join($p['oriWorkshops'] ?? []),
            ];
        }
        foreach ($notAttempted as $p) {
            $rows[] = [
                '',
                (string)($p['fullname'] ?? '—'),
                (string)($p['country'] ?? ''),
                $this->cfg->statusLabels[$p['statusKey'] ?? 'invited'] ?? '—',
                '',
                '',
                '',
                $join($p['oriLocations'] ?? []),
                $join($p['oriWorkshops'] ?? []),
            ];
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="candidats_classement.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output','w');
        fputcsv($out, ['Rang','Nom','Pays','Statut','Score (%)','Note','Barème','Lieux choisis','Ateliers choisis'],';');
        foreach ($rows as $r) fputcsv($out,$r,';');
        fclose($out);
        exit();
    }
}

/* ============================================================
|  Decorator / Presenter (pour le rendu)
|============================================================ */
final class CandidatePresenter {
    private $c; private $cfg;
    public function __construct($candidate, AppConfig $cfg){ $this->c=$candidate; $this->cfg=$cfg; }

    public function name(){ $n = (string)($this->c['fullname'] ?? '—'); return $n===''?'—':$n; }
    public function country(){ return (string)($this->c['country'] ?? ''); }
    public function flag(){ return (string)($this->c['flagPath'] ?? ''); }
    public function statusKey(){ return (string)($this->c['statusKey'] ?? 'invited'); }
    public function statusLabel(){ $k=$this->statusKey(); return $this->cfg->statusLabels[$k] ?? '—'; }
    public function pct(){ return (float)($this->c['pct'] ?? 0); }
    public function note(){ return (int)($this->c['sumScore'] ?? 0); }
    public function bareme(){ return (int)($this->c['sumTotal'] ?? 0); }
    public function attempted(){ return !empty($this->c['attempted']); }
    public function locs(){ return (array)($this->c['oriLocations'] ?? []); }
    public function works(){ return (array)($this->c['oriWorkshops'] ?? []); }

    public function scoreClass(){
        if (!$this->attempted()) return 'score-neutral';
        $p=$this->pct();
        if ($p>=80) return 'score-green';
        if ($p>=51) return 'score-yellow';
        return 'score-orange';
    }
    public function statusBadgeClass(){
        $map=['started'=>'badge-started','awaiting'=>'badge-awaiting','done'=>'badge-done'];
        return isset($map[$this->statusKey()]) ? $map[$this->statusKey()] : 'badge-started';
    }
    public function avatarImg() {
        $g = strtolower((string)($this->c['gender'] ?? ''));
        return ($g==='f'||$g==='female'||$g==='femme'||$g==='feminin')
            ? '../../public/images/femme.png'
            : '../../public/images/enfant.png';
    }

    public function dataOloc(){ return htmlspecialchars(Arrays::strJoin($this->locs()),ENT_QUOTES,'UTF-8'); }
    public function dataOwork(){ return htmlspecialchars(Arrays::strJoin($this->works()),ENT_QUOTES,'UTF-8'); }

    /** JSON : [{label, score, total, pct}] pour chaque paper testé */
    public function dataResults($paperLabelMap = []) {
        $results = $this->c['allPaperResults'] ?? [];
        if ($results instanceof \MongoDB\Model\BSONArray) {
            $results = $results->getArrayCopy();
        }
        $out = [];
        foreach ((array)$results as $r) {
            if ($r instanceof \MongoDB\Model\BSONDocument) {
                $r = $r->getArrayCopy();
            } else {
                $r = (array)$r;
            }
            $pid   = isset($r['_id']) ? (string)$r['_id'] : '';
            $score = (int)($r['score'] ?? 0);
            $total = (int)($r['total'] ?? 0);
            $pct   = $total > 0 ? round($score / $total * 100, 1) : 0;
            $label = ($pid !== '' && isset($paperLabelMap[$pid])) ? $paperLabelMap[$pid] : 'Test inconnu';
            $out[] = ['label' => $label, 'score' => $score, 'total' => $total, 'pct' => $pct];
        }
        return htmlspecialchars(json_encode($out, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }

    /** JSON : [{label, values}] pour chaque question d'orientation du paper */
    public function dataOriAnswers($paperOriQs = []) {
        $oriArr = $this->c['oriAnsArr'] ?? [];
        if ($oriArr instanceof \MongoDB\Model\BSONArray) {
            $oriArr = $oriArr->getArrayCopy();
        }
        // Map questionId -> selected values
        $answerMap = [];
        foreach ((array)$oriArr as $ans) {
            if ($ans instanceof \MongoDB\Model\BSONDocument) {
                $ans = $ans->getArrayCopy();
            } else {
                $ans = (array)$ans;
            }
            $qid = (string)($ans['id'] ?? '');
            $sel = isset($ans['selected']) ? $ans['selected'] : [];
            if ($sel instanceof \MongoDB\Model\BSONArray) $sel = $sel->getArrayCopy();
            if ($qid !== '') $answerMap[$qid] = array_map('strval', (array)$sel);
        }
        $out = [];
        if (!empty($paperOriQs)) {
            foreach ($paperOriQs as $qid => $q) {
                $out[] = ['label' => $q['label'], 'values' => $answerMap[$qid] ?? []];
            }
        } else {
            foreach ($answerMap as $qid => $vals) {
                $out[] = ['label' => $qid, 'values' => $vals];
            }
        }
        return htmlspecialchars(json_encode($out, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    }
}

/* ============================================================
|  Bootstrap App
|============================================================ */
$cfg = new AppConfig();
$req = RequestParams::fromGlobals();
if (!$req->isExport) setPageBackground("bg-dashboard");

try {
    $mongo    = new MongoFactory($cfg);
    $usersCol = $mongo->col('users');
    $oriQCol  = $mongo->col('orientation_questions');
    $papersCol= $mongo->col('papers');

    // ── Campagnes disponibles ──────────────────────────────────────
    $campaignRepo       = new CampaignRepository($usersCol);
    $availableCampaigns = $campaignRepo->getDistinct();
    // ──────────────────────────────────────────────────────────────

    // ── Questions d'orientation du paper sélectionné ───────────────
    $paperOriLoader    = new PaperOrientationLoader($papersCol, $oriQCol);
    $paperOriQuestions = $paperOriLoader->load($req->paperOid);

    // Si pas de paper direct mais une campagne est sélectionnée :
    // chercher le paper par son label (= nom de la campagne)
    if (empty($paperOriQuestions) && $req->campaign) {
        $campPaper = $papersCol->findOne(
            ['active' => true, 'label' => $req->campaign],
            ['projection' => ['_id' => 1]]
        );
        if ($campPaper) {
            $paperOriQuestions = $paperOriLoader->load($campPaper['_id']);
        }
    }
    // ──────────────────────────────────────────────────────────────

    $qb   = new CandidateQueryBuilder($cfg, $req, $paperOriQuestions);
    $repo = new CandidateRepository($usersCol, $qb);
    $candidates = $repo->fetchAll();

    $svc = new CandidateService($cfg);
    $candidatesFiltered = $svc->filterByStatus($candidates, $req->status);

    // Filtrage orientation : dynamique si paper avec orientation, sinon classique oloc/owork
    if (!empty($paperOriQuestions) && !empty($req->oriFilters)) {
        $candidatesFiltered = $svc->filterByOrientationDynamic($candidatesFiltered, $req->oriFilters);
    } else {
        $candidatesFiltered = $svc->filterByOrientation($candidatesFiltered, $req->oloc, $req->owork);
    }

    list($attempted, $notAttempted) = $svc->splitAttempted($candidatesFiltered);
    $svc->sortByPctDescThenName($attempted);
    $leaderboard     = array_slice($attempted, 0, 3);
    $othersAttempted = array_slice($attempted, 3);
    $statusCounts    = $svc->statusCounts($candidates);

    // Carte paperId -> label (pour la modal)
    $paperLabelMap = [];
    try {
        $allPapers = $papersCol->find([], ['projection' => ['_id' => 1, 'label' => 1]]);
        foreach ($allPapers as $paper) {
            $paperLabelMap[(string)$paper['_id']] = (string)($paper['label'] ?? '?');
        }
    } catch (Throwable $e) {}

    // Distinct options orientation (mode classique, sans paper sélectionné)
    $optRepo = new OrientationOptionsRepository($oriQCol, $cfg);
    list($distinctLoc, $distinctWork) = $optRepo->getDistinctFromQuestions($candidates);

    // Export
    if ($req->isExport) {
        (new CsvExporter($cfg))->export($leaderboard, $othersAttempted, $notAttempted);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "<pre>Erreur: ".htmlspecialchars($e->getMessage())."</pre>";
    exit();
}

/* ============================================================
|  Helpers côté vue
|============================================================ */
function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function number1($f){ return number_format((float)$f,1); }

include "./partials/header.php";
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
<title>Etat d'Avancement des Campagnes de Recrutement | CFAO Mobility Academy</title>

<style>
body,.campaign-wrap{font-size:1.1rem}
.card-header-white{font-size:1.15rem}
.candidate-name{font-size:1.2rem}
.badge-score,.badge-status{font-size:1rem}
.card-title-white{background:#fff!important;color:#0f172a!important;border:1px solid rgba(0,0,0,.08);border-radius:16px}
.glass-card{background:rgba(255,255,255,.78)!important;backdrop-filter:blur(18px)!important;-webkit-backdrop-filter:blur(18px)!important;border:1px solid rgba(255,255,255,.22)!important;box-shadow:0 8px 32px rgba(31,38,135,.18)!important;border-radius:16px!important;transition:transform .25s ease,box-shadow .25s ease!important}
.glass-card:hover{transform:translateY(-3px)}
.card-header-white{background:#fff;color:#0f172a;font-weight:800;padding:12px 16px;border-radius:14px 14px 0 0;border-bottom:1px solid rgba(0,0,0,.05)}
.campaign-wrap{border-radius:16px;padding:18px}
.sticky-sidebar{position:sticky;top:84px}
.sidebar-card .section-title{font-weight:800;color:#0f172a;margin-bottom:.5rem}
.sidebar-card .section-hint{font-size:12px;color:#818181}
.filter-pills{display:flex;flex-wrap:wrap;gap:8px}
.filter-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;font-weight:700;font-size:13px;border:1px solid rgba(15,23,42,.08);background:#ffffffcc;color:#0f172a;text-decoration:none}
.filter-pill .count{font-weight:900;font-size:12px;opacity:.75}
.filter-pill.active{background:#0ea5e9;color:#fff;border-color:#38bdf8}
.filter-pill:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(0,0,0,.08)}
.filter-orientation{display:flex;gap:12px;flex-wrap:wrap}
.filter-orientation select,.filter-orientation input[type="date"]{border-radius:10px;padding:8px 10px;border:1px solid rgba(0,0,0,.15);background:#fff;min-width:220px;font-weight:600;width:100%}
/* Badge campagne dans le titre */
.campaign-badge{display:inline-block;background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-size:.7em;font-weight:700;padding:3px 10px;border-radius:999px;vertical-align:middle;margin-left:10px;letter-spacing:.3px}
/* Select campagne spécial */
.select-campaign{border-radius:10px;padding:8px 10px;border:2px solid #6366f1;background:#f5f3ff;font-weight:700;width:100%;color:#3730a3;cursor:pointer}
.select-campaign:focus{outline:none;box-shadow:0 0 0 3px rgba(99,102,241,.25)}
.podium{display:grid;grid-template-columns:1fr 1.2fr 1fr;gap:16px;align-items:end}
.podium .pod{text-align:center;padding:16px;border-radius:14px;background:rgba(255,255,255,.9);border:1px solid rgba(0,0,0,.06);cursor:pointer}
.pod .rank{font-weight:900;width:34px;height:34px;border-radius:50%;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;color:#fff}
.pod-1 .rank{background:linear-gradient(135deg,#f59e0b,#ef4444)}
.pod-2 .rank{background:linear-gradient(135deg,#94a3b8,#64748b)}
.pod-3 .rank{background:linear-gradient(135deg,#a78bfa,#7c3aed)}
.pod .name{font-weight:900;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pod .meta{font-size:12px;color:#64748b}
.badge-score{display:inline-block;min-width:70px;text-align:center;font-weight:900;font-size:.95rem;padding:.45rem .7rem;border-radius:12px;border:1px solid transparent;margin-top:8px}
.score-green{background:#6CF95D;color:#064e3b;border-color:#bffbb8}
.score-yellow{background:#FAF75A;color:#7a6100;border-color:#f9f6a1}
.score-orange{background:#FB9258;color:#6b2c0c;border-color:#fcc1a3}
.score-neutral{background:#e5e7eb;color:#374151;border-color:#d1d5db}
.badge-status{font-size:12px;font-weight:800;padding:.35rem .6rem;border-radius:999px;display:inline-flex;align-items:center;gap:6px;border:1px solid transparent}
.badge-started{background:#ede9fe;color:#5b21b6;border-color:#ddd6fe}
.badge-awaiting{background:#fef3c7;color:#92400e;border-color:#fde68a}
.badge-done{background:#d1fae5;color:#065f46;border-color:#a7f3d0}
.table-ranking{width:100%;border-collapse:separate;border-spacing:0;border-radius:14px;overflow:hidden}
.table-ranking thead{background:linear-gradient(90deg,#eef2ff,#e0f2fe);color:#0f172a;font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.5px}
.table-ranking th,.table-ranking td{padding:12px 14px}
.table-ranking tbody tr{background:rgba(255,255,255,.9);cursor:pointer}
.table-ranking tbody tr+tr td{border-top:1px solid rgba(0,0,0,.04)}
.table-ranking td:last-child{text-align:right}
.candidate-card{border-radius:14px;padding:16px;border:1px solid rgba(0,0,0,.06);display:flex;gap:14px;align-items:center;justify-content:space-between;background:rgba(255,255,255,.86);backdrop-filter:blur(20px);cursor:pointer}
.candidate-left{display:flex;gap:14px;align-items:center;min-width:0}
.candidate-avatar{width:46px;height:46px;border-radius:12px;overflow:hidden;flex:0 0 46px;background:#eef2ff;box-shadow:inset 0 0 0 2px rgba(30,64,175,.12)}
.candidate-name{font-weight:900;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.candidate-sub{font-size:12px;color:#4b5563;display:flex;align-items:center;gap:8px}
.flag-img{width:24px;height:16px;object-fit:cover;border-radius:3px;box-shadow:0 0 0 1px rgba(0,0,0,.15)}
.card-grid .col{margin-bottom:14px}
</style>

<?php openBackgroundContainer(); ?>
<div class="container-xxl campaign-wrap">

  <!-- Titre -->
  <div class="card shadow-sm mb-4 card-title-white">
    <div class="card-body p-4">
      <h1 class="text-dark fw-bold text-center fs-1">
        <i class="fa-solid fa-id-card-clip me-2"></i>Etat d'Avancement des Campagnes de Recrutement
        <?php if ($req->campaign): ?>
          <span class="campaign-badge"><i class="fa-solid fa-flag me-1"></i><?= h($req->campaign) ?></span>
        <?php endif; ?>
      </h1>
      <div class="text-center text-gray-600 mt-1">Leaderboard, filtres Orientation & export Excel</div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Contenu gauche -->
    <div class="col-lg-8 col-xl-9">

      <!-- Leaderboard -->
      <div class="card glass-card mb-4">
        <div class="card-header-white"><i class="fa-solid fa-trophy me-2"></i>Leaderboard — Top 3</div>
        <div class="card-body">
          <?php if (empty($leaderboard)): ?>
            <div class="text-muted">Aucun candidat classé (personne n'a encore complété un QCM factuel).</div>
          <?php else: ?>
            <div class="podium">
              <?php foreach ([1,2,3] as $i):
                if (!isset($leaderboard[$i-1])) { echo "<div></div>"; continue; }
                $p = new CandidatePresenter($leaderboard[$i-1], $cfg);
              ?>
                <div class="pod pod-<?= $i ?> js-cand"
                     data-name="<?= h($p->name()) ?>"
                     data-country="<?= h($p->country()) ?>"
                     data-flag="<?= h($p->flag()) ?>"
                     data-score="<?= number1($p->pct()) ?>"
                     data-status="<?= h($p->statusLabel()) ?>"
                     data-oloc="<?= $p->dataOloc() ?>"
                     data-owork="<?= $p->dataOwork() ?>"
                     data-results="<?= $p->dataResults($paperLabelMap) ?>"
                     data-ori="<?= $p->dataOriAnswers($paperOriQuestions) ?>">
                  <div class="rank">#<?= $i ?></div>
                  <div class="name"><?= h($p->name()) ?></div>
                  <div class="meta">
                    <?php if ($p->flag()): ?><img src="<?= h($p->flag()) ?>" class="flag-img" alt="flag"> <?php endif; ?>
                    <?= h($p->country()) ?>
                  </div>
                  <div class="badge-score <?= $p->scoreClass() ?>">
                    <?= number1($p->pct()) ?>%
                    <span class="d-block small">(<?= (int)$p->note() ?>/<?= (int)$p->bareme() ?>)</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Classement -->
      <div class="card glass-card mb-5">
        <div class="card-header-white"><i class="fa-solid fa-list-ol me-2"></i>Classement (du 4e au dernier testé)</div>
        <div class="card-body">
          <?php if (empty($othersAttempted) && !empty($leaderboard)): ?>
            <div class="text-muted">Seuls les 3 premiers sont disponibles pour l'instant.</div>
          <?php elseif (empty($attempted)): ?>
            <div class="text-muted">Personne n'a encore terminé un QCM factuel.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table-ranking">
                <thead><tr>
                  <th style="width:80px">Rang</th>
                  <th>Nom</th>
                  <th>Pays</th>
                  <th>Lieux</th>
                  <th>Ateliers</th>
                  <th>Statut</th>
                  <th class="text-end">Score</th>
                </tr></thead>
                <tbody>
                <?php $rank=4; foreach ($othersAttempted as $row):
                    $p = new CandidatePresenter($row,$cfg);
                    $locStr = h(Arrays::strJoin($p->locs()));
                    $wrkStr = h(Arrays::strJoin($p->works()));
                ?>
                  <tr class="js-cand"
                      data-name="<?= h($p->name()) ?>"
                      data-country="<?= h($p->country()) ?>"
                      data-flag="<?= h($p->flag()) ?>"
                      data-score="<?= number1($p->pct()) ?>"
                      data-status="<?= h($p->statusLabel()) ?>"
                      data-oloc="<?= $locStr ?>"
                      data-owork="<?= $wrkStr ?>"
                      data-results="<?= $p->dataResults($paperLabelMap) ?>"
                      data-ori="<?= $p->dataOriAnswers($paperOriQuestions) ?>">
                      <td><strong>#<?= $rank++ ?></strong></td>
                      <td><?= h($p->name()) ?></td>
                      <td><?= $p->flag()?("<img src='".h($p->flag())."' class='flag-img' alt='flag'> "):""; ?><?= h($p->country()) ?></td>
                      <td><?= $locStr ?></td>
                      <td><?= $wrkStr ?></td>
                      <td><span class="badge-status <?= $p->statusBadgeClass() ?>"><?= h($p->statusLabel()) ?></span></td>
                      <td>
                        <span class="badge-score <?= $p->scoreClass() ?>">
                          <?= number1($p->pct()) ?>%<br>(<?= (int)$p->note() ?>/<?= (int)$p->bareme() ?>)
                        </span>
                      </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Vue cartes (collapse) -->
      <div class="card glass-card has-white-header mb-4">
        <div class="card-header-white d-flex justify-content-between align-items-center"
             data-bs-toggle="collapse" data-bs-target="#cardsCollapse"
             aria-expanded="true" aria-controls="cardsCollapse" style="cursor:pointer;">
          <div>
            <i class="fa-solid fa-users me-2"></i>Vue cartes (tous les candidats filtrés)
            <span class="badge bg-primary ms-2" id="cardCount"><?= count($candidatesFiltered) ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <small class="text-muted collapse-status">Cliquer pour replier</small>
            <i class="fa-solid fa-chevron-down collapse-icon" style="transition:transform .3s ease;"></i>
          </div>
        </div>

        <div class="collapse" id="cardsCollapse">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
              <small class="text-muted"><i class="fa-solid fa-info-circle me-1"></i><?= count($candidatesFiltered) ?> candidat(s) affiché(s)</small>
              <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="viewMode" id="viewGrid" checked>
                <label class="btn btn-outline-secondary" for="viewGrid" title="Vue grille"><i class="fa-solid fa-grip"></i></label>
                <input type="radio" class="btn-check" name="viewMode" id="viewList">
                <label class="btn btn-outline-secondary" for="viewList" title="Vue liste"><i class="fa-solid fa-list"></i></label>
              </div>
            </div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-2 row-cols-xl-3 card-grid" id="cardsContainer">
              <?php if (empty($candidatesFiltered)): ?>
                <div class="col"><div class="candidate-card">
                  <div class="candidate-left">
                    <div class="candidate-avatar"></div>
                    <div><div class="candidate-name">Aucun candidat</div><div class="candidate-sub">—</div></div>
                  </div>
                  <div class="text-end"><span class="badge-status badge-started">—</span><div class="mt-2"><span class="badge-score score-neutral">—</span></div></div>
                </div></div>
              <?php else: foreach ($candidatesFiltered as $row):
                $p = new CandidatePresenter($row,$cfg);
                $locStr = h(Arrays::strJoin($p->locs())); $wrkStr = h(Arrays::strJoin($p->works()));
              ?>
                <div class="col candidate-col">
                  <div class="candidate-card js-cand"
                       data-name="<?= h($p->name()) ?>"
                       data-country="<?= h($p->country()) ?>"
                       data-flag="<?= h($p->flag()) ?>"
                       data-score="<?= number1($p->pct()) ?>"
                       data-status="<?= h($p->statusLabel()) ?>"
                       data-oloc="<?= $locStr ?>"
                       data-owork="<?= $wrkStr ?>"
                       data-results="<?= $p->dataResults($paperLabelMap) ?>"
                       data-ori="<?= $p->dataOriAnswers($paperOriQuestions) ?>">
                    <div class="candidate-left">
                      <div class="candidate-avatar"><img src="<?= h($p->avatarImg()) ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:12px"></div>
                      <div>
                        <div class="candidate-name"><?= h($p->name()) ?></div>
                        <div class="candidate-sub">
                          <?php if ($p->flag()): ?><img src="<?= h($p->flag()) ?>" class="flag-img" alt="flag"><?php endif; ?>
                          <span><?= h($p->country() ?: '—') ?></span>
                        </div>
                      </div>
                    </div>
                    <div class="text-end">
                      <span class="badge-status <?= $p->statusBadgeClass() ?>"><i class="fa-solid fa-circle-dot"></i> <?= h($p->statusLabel()) ?></span>
                      <div class="mt-2"><span class="badge-score <?= $p->scoreClass() ?>"><?= $p->attempted() ? number1($p->pct()).'%' : '—' ?></span></div>
                    </div>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>

            <div class="border-top pt-3 mt-3">
              <div class="row text-center">
                <div class="col-6 col-md-3"><div class="small text-muted">Total</div><div class="fw-bold"><?= count($candidatesFiltered) ?></div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Ont tenté</div><div class="fw-bold text-success"><?= count($attempted) ?></div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">N'ont pas tenté</div><div class="fw-bold text-warning"><?= count($notAttempted) ?></div></div>
                <div class="col-6 col-md-3"><div class="small text-muted">Taux de participation</div><div class="fw-bold text-info"><?= count($candidatesFiltered)>0 ? round((count($attempted)/count($candidatesFiltered))*100) : 0 ?>%</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <style>
        .collapse-status{font-size:.75rem;opacity:.7}
        .collapse-icon{font-size:.875rem;transition:transform .3s ease!important}
        [aria-expanded="false"] .collapse-icon{transform:rotate(180deg)}
        .card-header-white[data-bs-toggle="collapse"]:hover{background-color:#f8fafc!important;transition:background-color .2s ease}
        .candidate-col{transition:all .3s ease}
        .view-list .card-grid{display:block!important}
        .view-list .candidate-col{width:100%!important;margin-bottom:8px}
        .view-list .candidate-card{border-radius:8px;padding:12px 16px}
        @media (max-width:768px){
          .card-header-white{flex-direction:column;align-items:flex-start!important;gap:8px}
          .card-header-white>div:last-child{align-self:flex-end}
        }
      </style>

      <script>
        document.addEventListener('DOMContentLoaded', function(){
          const collapseElement = document.getElementById('cardsCollapse');
          const statusElement = document.querySelector('.collapse-status');
          if (collapseElement && statusElement) {
            collapseElement.addEventListener('show.bs.collapse', ()=>{ statusElement.textContent='Cliquer pour replier'; });
            collapseElement.addEventListener('hide.bs.collapse', ()=>{ statusElement.textContent='Cliquer pour déplier'; });
          }
          const viewModeInputs = document.querySelectorAll('input[name="viewMode"]');
          const cardsContainer = document.getElementById('cardsContainer');
          viewModeInputs.forEach(input=>{
            input.addEventListener('change', function(){
              if (this.id === 'viewList') {
                cardsContainer.classList.add('view-list');
                cardsContainer.classList.remove('row','row-cols-1','row-cols-sm-2','row-cols-lg-2','row-cols-xl-3');
              } else {
                cardsContainer.classList.remove('view-list');
                cardsContainer.classList.add('row','row-cols-1','row-cols-sm-2','row-cols-lg-2','row-cols-xl-3');
              }
            });
          });

          function setScoreChip(el, value){
            const v = parseFloat(value||0);
            let cls='score-orange';
            if (v>=80) cls='score-green';
            else if (v>=51) cls='score-yellow';
            el.className='badge-score '+cls;
            el.textContent = isFinite(v) ? (v.toFixed(1)+'%') : '—';
          }
          function escHtml(str){
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
          }
          function scoreClass(pct){
            if (pct>=80) return 'score-green';
            if (pct>=51) return 'score-yellow';
            return 'score-orange';
          }
          function openModalFrom(el){
            const name    = el.getAttribute('data-name')    || '—';
            const country = el.getAttribute('data-country') || '—';
            const flag    = el.getAttribute('data-flag')    || '';
            const score   = el.getAttribute('data-score')   || '';
            const status  = el.getAttribute('data-status')  || '—';
            const resultsJson = el.getAttribute('data-results') || '[]';
            const oriJson     = el.getAttribute('data-ori')     || '[]';

            document.getElementById('mName').textContent    = name;
            document.getElementById('mCountry').textContent = country;
            const flagEl = document.getElementById('mFlag');
            if (flag){ flagEl.src=flag; flagEl.style.display='inline-block'; } else { flagEl.style.display='none'; }
            document.getElementById('mStatus').textContent = status;
            setScoreChip(document.getElementById('mScore'), score);

            // ── Résultats par test ───────────────────────────────
            var results = [];
            try { results = JSON.parse(resultsJson); } catch(e){}
            var mResultsBody = document.getElementById('mResultsBody');
            if (!results.length){
              mResultsBody.innerHTML = '<span class="text-muted">Aucun résultat enregistré.</span>';
            } else {
              var html = '<table class="table table-sm table-bordered mb-0"><thead><tr>'
                       + '<th>Test</th><th>Note</th><th>Score %</th>'
                       + '</tr></thead><tbody>';
              for (var i=0;i<results.length;i++){
                var r   = results[i];
                var pct = parseFloat(r.pct||0);
                var cls = scoreClass(pct);
                html += '<tr><td>' + escHtml(r.label) + '</td>'
                      + '<td>' + r.score + '/' + r.total + '</td>'
                      + '<td><span class="badge-score ' + cls + '" style="min-width:64px;display:inline-block;text-align:center">'
                      + pct.toFixed(1) + '%</span></td></tr>';
              }
              html += '</tbody></table>';
              mResultsBody.innerHTML = html;
            }

            // ── Réponses d'orientation ───────────────────────────
            var oriAnswers = [];
            try { oriAnswers = JSON.parse(oriJson); } catch(e){}
            var mOriBody = document.getElementById('mOriBody');
            if (!oriAnswers.length){
              mOriBody.innerHTML = '<span class="text-muted">Aucune réponse d\'orientation.</span>';
            } else {
              var oriHtml = '';
              for (var j=0;j<oriAnswers.length;j++){
                var q    = oriAnswers[j];
                var vals = (q.values && q.values.length) ? q.values.join(', ') : '—';
                oriHtml += '<div class="mb-1">'
                         + '<span class="text-muted fw-bold">' + escHtml(q.label) + ' :</span> '
                         + escHtml(vals)
                         + '</div>';
              }
              mOriBody.innerHTML = oriHtml;
            }

            new bootstrap.Modal(document.getElementById('candModal')).show();
          }
          document.querySelectorAll('.js-cand').forEach(el=>{
            el.addEventListener('click', function(){ openModalFrom(this); });
          });
        });
      </script>

    </div><!-- /gauche -->

    <!-- Sidebar droite -->
    <div class="col-lg-4 col-xl-3">
      <div class="sticky-sidebar">
        <div class="card glass-card sidebar-card">
          <div class="card-header-white d-flex justify-content-between align-items-center">
            <div><i class="fa-solid fa-sliders me-2"></i>Filtres</div>
            <div>
              <a class="btn btn-sm btn-outline-primary" href="<?= h(UrlBuilder::build($req->baseParams, ['export'=>'1'])) ?>">
                <i class="fa-solid fa-file-export me-1"></i> Exporter
              </a>
            </div>
          </div>
          <div class="card-body">

            <!-- ═══════════════════════════════════════════════
                 NOUVEAU : Filtre Campagne de recrutement
            ════════════════════════════════════════════════ -->
            <div class="mb-3">
              <div class="section-title mb-1">
                <i class="fa-solid fa-flag me-2 text-indigo-600" style="color:#6366f1"></i>Campagne de recrutement
              </div>
              <div class="section-hint mb-2">Filtrer par campagne (champ <code>role</code>)</div>
              <form method="get" id="formCampaign">
                <?php
                  // Conserver les autres filtres actifs
                  foreach (['country','agency','specialty','status','oloc','owork','paper','date'] as $keep) {
                    if (!empty($_GET[$keep])) {
                      echo '<input type="hidden" name="'.h($keep).'" value="'.h($_GET[$keep]).'">';
                    }
                  }
                ?>
                <select name="campaign"
                        class="select-campaign"
                        onchange="this.form.submit()"
                        title="Sélectionner une campagne">
                  <option value="">— Toutes les campagnes —</option>
                  <?php foreach ($availableCampaigns as $camp): ?>
                    <option value="<?= h($camp) ?>" <?= ($req->campaign === $camp) ? 'selected' : '' ?>>
                      <?= h($camp) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($req->campaign): ?>
                  <a href="<?= h(UrlBuilder::build($req->baseParams, ['campaign'=>null])) ?>"
                     class="btn btn-sm btn-outline-secondary mt-2 w-100">
                    <i class="fa-solid fa-times me-1"></i>Effacer la campagne
                  </a>
                <?php endif; ?>
              </form>
            </div>
            <!-- ═══════════════════════════════════════════════ -->

            <hr class="my-3">

            <div class="mb-3">
              <div class="section-title mb-2">Statut</div>
              <div class="filter-pills">
                <?php
                  $allUrl    = h(UrlBuilder::build($req->baseParams, ['status'=>null]));
                  $activeAll = $req->status ? '' : 'active';
                  echo "<a class='filter-pill $activeAll' href='$allUrl'><i class='fa-solid fa-layer-group'></i> Tous <span class=\"count\">".count($candidates)."</span></a>";
                  foreach ($cfg->statusOrder as $sk) {
                    $url = h(UrlBuilder::build($req->baseParams, ['status'=>$sk]));
                    $active = ($req->status === $sk) ? 'active' : '';
                    $label  = h($cfg->statusLabels[$sk]);
                    $count  = (int)($statusCounts[$sk] ?? 0);
                    echo "<a class='filter-pill $active' href='$url'><i class='fa-solid fa-circle-dot'></i> $label <span class='count'>$count</span></a>";
                  }
                ?>
              </div>
            </div>

            <hr class="my-3">

            <div class="mb-2 section-title"><i class="fa-solid fa-location-dot me-2"></i>Orientation</div>
            <div class="section-hint mb-2">
              <?= !empty($paperOriQuestions) ? 'Filtres selon les questions du test sélectionné' : 'Filtrer par lieux & ateliers choisis' ?>
            </div>

            <form method="get" class="filter-orientation">
              <?php
                foreach (['country','agency','specialty','status','paper','campaign'] as $keep) {
                  if (!empty($_GET[$keep])) {
                    echo '<input type="hidden" name="'.h($keep).'" value="'.h($_GET[$keep]).'">';
                  }
                }
              ?>

              <!-- Filtre Date -->
              <div class="w-100 mb-3">
                <label class="form-label mb-1 fw-bold"><i class="fa-solid fa-calendar-day me-1"></i>Date de composition</label>
                <input type="date"
                       name="date"
                       class="form-control"
                       value="<?= h($req->dateFilter ?? '') ?>"
                       onchange="this.form.submit()"
                       placeholder="Choisir une date">
                <?php if ($req->dateFilter): ?>
                  <a href="<?= h(UrlBuilder::build($req->baseParams, ['date'=>null])) ?>"
                     class="btn btn-sm btn-outline-secondary mt-2 w-100">
                    <i class="fa-solid fa-times me-1"></i>Effacer la date
                  </a>
                <?php endif; ?>
              </div>

              <?php if (!empty($paperOriQuestions)): ?>
                <!-- ── Filtres dynamiques selon le paper ── -->
                <?php foreach ($paperOriQuestions as $qid => $q): ?>
                  <div class="w-100 mb-3">
                    <label class="form-label mb-1 fw-bold"><?= h($q['label']) ?></label>
                    <select name="ori[<?= h($qid) ?>]" class="form-select" onchange="this.form.submit()">
                      <option value="">— Tous —</option>
                      <?php foreach ($q['options'] as $opt): ?>
                        <option value="<?= h($opt) ?>"
                          <?= (isset($req->oriFilters[$qid]) && $req->oriFilters[$qid] === $opt) ? 'selected' : '' ?>>
                          <?= h($opt) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endforeach; ?>
                <?php if (!empty($req->oriFilters)): ?>
                  <a href="<?= h(UrlBuilder::build($req->baseParams, ['ori'=>null])) ?>"
                     class="btn btn-sm btn-outline-secondary w-100">
                    <i class="fa-solid fa-times me-1"></i>Effacer les filtres orientation
                  </a>
                <?php endif; ?>

              <?php else: ?>
                <!-- ── Filtres classiques Lieu + Atelier (pas de paper sélectionné) ── -->
                <div class="w-100 mb-3">
                  <label class="form-label mb-1 fw-bold">Lieux choisis</label>
                  <select name="oloc" class="form-select" onchange="this.form.submit()">
                    <option value="">— Tous —</option>
                    <?php foreach ($distinctLoc as $v): ?>
                      <option value="<?= h($v) ?>" <?= $req->oloc===$v?'selected':'' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="w-100">
                  <label class="form-label mb-1 fw-bold">Ateliers choisis</label>
                  <select name="owork" class="form-select" onchange="this.form.submit()">
                    <option value="">— Tous —</option>
                    <?php foreach ($distinctWork as $v): ?>
                      <option value="<?= h($v) ?>" <?= $req->owork===$v?'selected':'' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
            </form>

          </div>
        </div>
      </div>
    </div><!-- /droite -->
  </div><!-- /row -->

</div>
<?php closeBackgroundContainer(); ?>

<!-- Modal -->
<div class="modal fade" id="candModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered" style="max-width:760px">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2 flex-wrap">
          <span id="mName">—</span>
          <span class="badge rounded-pill bg-secondary" id="mStatus">—</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- En-tête : pays + score global -->
        <div class="d-flex align-items-center gap-3 mb-4">
          <img id="mFlag" src="" alt="" class="flag-img" style="display:none">
          <div class="fw-bold text-muted" id="mCountry">—</div>
          <div class="ms-auto badge-score" id="mScore">—</div>
        </div>

        <!-- Résultats par test -->
        <div class="p-3 border rounded-3 mb-3">
          <div class="fw-bold mb-2"><i class="fa-solid fa-chart-bar me-2"></i>Résultats par test</div>
          <div id="mResultsBody"><span class="text-muted">Aucun résultat.</span></div>
        </div>

        <!-- Réponses d'orientation -->
        <div class="p-3 border rounded-3">
          <div class="fw-bold mb-2"><i class="fa-solid fa-location-dot me-2"></i>Réponses d'orientation</div>
          <div id="mOriBody"><span class="text-muted">Aucune réponse d'orientation.</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <a class="btn btn-outline-primary" href="<?= h(UrlBuilder::build($req->baseParams, ['export'=>'1'])) ?>">
          <i class="fa-solid fa-file-export me-1"></i> Exporter Excel
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<?php include "./partials/footer.php"; ?>