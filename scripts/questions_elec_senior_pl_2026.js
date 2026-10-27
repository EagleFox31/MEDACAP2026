// =====================================================================
// MEDACAP — 73 questions Factuelles Senior "Electricité Poids Lourds"
// Orientation : SCENARIOS D'ATELIER / REPARATION (pas de diagnostic)
// Refs Fac2o263 -> Fac2o335 (Fac2o1..262 deja utilises, verifie en base)
//
// Usage :  mongo academy scripts/questions_elec_senior_pl_2026.js
//          (ou mongo 10.68.0.7:27017/academy scripts/...)
//
// ---------------------------------------------------------------------
// GABARIT DE REDACTION (applique aux 73 questions)
//
//   label = [SITUATION]  marque/vehicule PL + organe + etat constate
//           [CONSIGNE]   l'intervention qui est confiee au technicien
//           [QUESTION]   porte sur l'action, l'ordre ou la precaution
//
//   propositions : les 4 sont des ACTIONS ou des CHOIX D'ATELIER.
//                  Les 3 distracteurs sont des erreurs de reparation
//                  plausibles, jamais des absurdites.
//
//   orientation  : "scenario_reparation" = la bonne reponse est un geste
//                  "savoir_ancre"        = la bonne reponse est une valeur
//                                          ou une reference, mais la
//                                          question reste posee dans un cas
//
//   Aucune question ne demande une valeur de mesure a relever ni un code
//   defaut a interpreter : le diagnostic reste hors perimetre Senior ici.
// ---------------------------------------------------------------------
//
// ETAPE 1 = insertion des 73 questions dans la nouvelle specialite
// ETAPE 2 = creation du quiz (Electricite Poids Lourds / Factuel / Senior)
// ETAPE 3 = rattachement aux vehicles des 5 marques camions
//
// Le script est idempotent : il abandonne si les refs ou la specialite
// existent deja. Un rollback complet figure en fin de fichier.
//
// POURQUOI UNE NOUVELLE SPECIALITE PLUTOT QU'UN AJOUT AU QUIZ EXISTANT
// Le quiz "Electricite et Electronique / Factuel / Senior" est un document
// UNIQUE reference par 18 marques (TOYOTA, PEUGEOT, JCB, LOVOL, BYD... autant
// que RENAULT TRUCK ou HINO). Y verser ces 73 questions les aurait rendues
// visibles sur les VL et les chariots elevateurs. Et creer un second quiz au
// meme triplet aurait produit le premier doublon de (speciality, type, level)
// de la base, invariant verifie sur 136/136 quiz et suppose par le code
// (voir specQuizMap dans views/measure/create_fixed_paper.php:28).
//
// NOTE sur le champ "orientation" : il n'est lu par aucune requete de
// l'application. Il sert uniquement au controle de ratio ci-dessous et
// aux futurs ajouts de banque. Un champ "brandScope" existe deja dans la
// collection (25 docs Carrosserie, valeur "ALL") et n'est lui non plus
// lu nulle part — voir views/explore/importeQuestion_carrosserie.php:158.
// =====================================================================

var CREATED = "15-08-26";

// OPTION B retenue : les 73 questions constituent une SPECIALITE A PART ENTIERE.
// Motif : un quiz est identifie par (speciality, type, level), triplet UNIQUE
// sur les 136 quiz de la base. Il n'existe aucune dimension "marque" dans la
// collection quizzes. Le seul patron natif de cantonnement est donc une
// specialite que seuls certains vehicles portent — exactement le cas de
// "Freinage Electromagnetique", porte par le seul TOYOTA BT.
var SPEC_PL = "Electricité Poids Lourds";

// Perimetre retenu : les 5 marques camions, alignees sur la constante BRANDS de
// scripts/create_mecanicien_pl_paper.php. KING LONG (bus) est hors perimetre.
var BRANDS_PL = ["RENAULT TRUCK", "MERCEDES TRUCK", "SINOTRUK", "HINO", "FUSO"];

var SCEN = "scenario_reparation";
var SAVOIR = "savoir_ancre";

function Q(ref, spec, orientation, label, p1, p2, p3, p4, answerIndex) {
  var props = [p1, p2, p3, p4];
  return {
    image: null,
    ref: ref,
    label: label,
    proposal1: p1,
    proposal2: p2,
    proposal3: p3,
    proposal4: p4,
    answer: props[answerIndex - 1],
    speciality: spec,
    type: "Factuelle",
    level: "Senior",
    orientation: orientation,
    active: true,
    created: CREATED
  };
}

var QUESTIONS = [

// ---------------------------------------------------------------------
// BLOC 1 — Securite et consignation avant intervention (9)
// ---------------------------------------------------------------------
Q("Fac2o263", SPEC_PL, SCEN,
  "Un Renault Trucks T est à l'atelier pour le remplacement du faisceau de cabine. Le chef d'atelier vous confie l'intervention. Vous ouvrez le coffre à batteries : par quelle action commencez-vous ?",
  "Vous déposez la cosse positive (+), puis la cosse négative (-)",
  "Vous déposez la cosse négative (-), puis la cosse positive (+)",
  "Vous déposez les deux cosses ensemble avec une clé isolée",
  "Vous coupez le contact et attaquez le faisceau sans déposer de cosse",
  2),

Q("Fac2o264", SPEC_PL, SCEN,
  "Sur un Mercedes Actros, un support de réservoir d'air est arraché. On vous demande de le ressouder à l'arc sur le châssis. Que faites-vous avant d'amorcer le premier cordon ?",
  "Vous déconnectez les batteries et fixez la pince de masse du poste sur le châssis, au plus près de la zone à souder",
  "Vous laissez les batteries branchées et fixez la pince de masse sur une borne de batterie",
  "Vous déposez le fusible du circuit d'éclairage et soudez batteries branchées",
  "Vous mettez le contact pour que les calculateurs écoulent le courant de soudage",
  1),

Q("Fac2o265", SPEC_PL, SCEN,
  "Vous venez de terminer le remontage du faisceau sur un HINO 500 et vous remettez les batteries en service. Dans quel ordre remontez-vous les cosses ?",
  "La cosse négative (-), puis la cosse positive (+)",
  "Les deux ensemble, pour éviter une micro-coupure des calculateurs",
  "La cosse positive (+), puis la cosse négative (-)",
  "Dans l'ordre inverse de la dépose, quel qu'il ait été",
  3),

Q("Fac2o266", SPEC_PL, SCEN,
  "Un SINOTRUK HOWO équipé d'un coupe-batterie est immobilisé pour une réparation de faisceau. Vous ouvrez le coupe-batterie et vous apprêtez à couper le premier fil. Quelle action reste indispensable ?",
  "Aucune, le coupe-batterie isole l'ensemble du véhicule",
  "Vous déposez la cosse négative : certains circuits (calculateurs, tachygraphe, mémoires) restent alimentés en amont du coupe-batterie",
  "Vous attendez dix minutes la décharge des condensateurs du réseau",
  "Vous verrouillez le coupe-batterie en position fermée",
  2),

Q("Fac2o267", SPEC_PL, SCEN,
  "Vous devez intervenir sur le faisceau moteur d'un Renault Trucks C, cabine basculée. Que faites-vous avant de vous engager sous la cabine ?",
  "Vous mettez en place la béquille de sécurité de cabine et vous assurez de son verrouillage",
  "Vous vous fiez au vérin hydraulique de basculement, qui maintient la cabine en position",
  "Vous calez la cabine avec une chandelle d'atelier posée sous le pare-chocs",
  "Vous basculez la cabine à mi-course pour limiter la hauteur en cas de retombée",
  1),

Q("Fac2o268", SPEC_PL, SCEN,
  "Un MERCEDES TRUCK est immobilisé plusieurs heures pour une réparation de faisceau, batteries déconnectées. Vous devez quitter votre poste et d'autres techniciens circulent dans l'atelier. Que faites-vous ?",
  "Vous prévenez oralement l'équipe avant de quitter le poste",
  "Vous posez une pancarte de consignation sur le volant et conservez la clé de contact sur vous",
  "Vous rebranchez les batteries pour que le véhicule reste manœuvrable",
  "Vous laissez la clé sur le contact pour que le véhicule puisse être déplacé si besoin",
  2),

Q("Fac2o269", SPEC_PL, SAVOIR,
  "Vous êtes affecté au remplacement des batteries d'un porteur HINO et passez au magasin chercher vos protections. Que demandez-vous ?",
  "Des gants de manutention en tissu et un masque anti-poussière",
  "Aucun équipement particulier, les batteries de PL étant scellées",
  "Des lunettes de protection et des gants résistants à l'acide sulfurique",
  "Des gants isolants haute tension et un tapis isolant",
  3),

Q("Fac2o270", SPEC_PL, SCEN,
  "Vous déposez les batteries d'un porteur. Votre collègue pose sa clé plate sur le dessus des deux bacs pour avoir les mains libres. Quelle action lui demandez-vous ?",
  "Aucune, les batteries étant déjà déconnectées",
  "De remplacer sa clé plate par une clé à pipe",
  "De déplacer la clé sur le seul bac négatif",
  "De retirer immédiatement la clé et d'isoler la borne + déposée : un pontage provoque un arc et une projection d'électrolyte",
  4),

Q("Fac2o271", SPEC_PL, SCEN,
  "Les batteries d'un porteur sont en charge à l'intérieur de l'atelier pendant que vous meulez un support à trois mètres de là. Quelle action s'impose ?",
  "Vous déplacez la charge ou le poste de meulage et ventilez la zone : la charge dégage de l'hydrogène",
  "Vous couvrez les batteries d'une bâche pour arrêter les projections",
  "Vous réduisez le courant du chargeur de moitié",
  "Vous poursuivez, trois mètres constituant une distance suffisante",
  1),

// ---------------------------------------------------------------------
// BLOC 2 — Circuit de charge / alternateur (8)
// ---------------------------------------------------------------------
Q("Fac2o272", SPEC_PL, SCEN,
  "L'alternateur d'un Renault Trucks C est à remplacer. Le véhicule est sur le pont, capot ouvert, et l'intervention vous est confiée. Par quelle action commencez-vous la dépose ?",
  "Vous détendez et déposez la courroie d'entraînement",
  "Vous déposez la cosse négative des batteries",
  "Vous déconnectez la borne B+ de l'alternateur",
  "Vous mettez le contact pour décharger le régulateur",
  2),

Q("Fac2o273", SPEC_PL, SCEN,
  "Vous reposez l'alternateur neuf sur un Renault Trucks C et arrivez au raccordement de la borne B+. Que faites-vous ?",
  "Vous serrez à fond pour garantir un contact franc",
  "Vous serrez à la main puis protégez la borne au ruban adhésif",
  "Vous graissez la borne avant serrage pour améliorer la conduction",
  "Vous serrez au couple prescrit, puis remettez en place le capuchon isolant d'origine",
  4),

Q("Fac2o274", SPEC_PL, SCEN,
  "Sur un FUSO Canter, la courroie d'accessoires entraînée par galet tendeur automatique est à reposer après remplacement de l'alternateur. Comment procédez-vous ?",
  "Vous détendez le galet avec l'outil prévu, engagez la courroie, puis relâchez le galet sans retension manuelle",
  "Vous engagez la courroie au tournevis en virant le moteur à la main",
  "Vous réglez la tension manuellement jusqu'à obtenir une flèche de 10 mm",
  "Vous bloquez le galet en position tendue une fois la courroie posée",
  1),

Q("Fac2o275", SPEC_PL, SAVOIR,
  "L'alternateur est reposé sur un porteur 24 V. Vous démarrez pour le contrôle de remise en service, moteur au ralenti accéléré. Quelle tension relevée aux bornes des batteries valide votre réparation ?",
  "Environ 24,0 V",
  "Environ 13,8 à 14,4 V",
  "Environ 27,5 à 28,8 V",
  "Environ 31 à 33 V",
  3),

Q("Fac2o276", SPEC_PL, SCEN,
  "Vous remplacez l'alternateur d'un tracteur routier dont le carnet mentionne des charges insuffisantes répétées. En dehors de l'organe lui-même, sur quoi intervenez-vous pendant le remontage ?",
  "Vous remplacez le fusible d'habitacle",
  "Vous déposez, nettoyez et resserrez les tresses de masse moteur et châssis",
  "Vous remplacez le contacteur à clé",
  "Vous remplacez le filtre à gazole",
  2),

Q("Fac2o277", SPEC_PL, SCEN,
  "L'alternateur déposé d'un Actros porte une poulie à roue libre (découpleur) et vous devez équiper l'alternateur neuf. Que faites-vous ?",
  "Vous bloquez la poulie avec une goupille avant la repose",
  "Vous montez une poulie fixe standard, moins coûteuse",
  "Vous transférez la poulie d'origine au burin sur l'alternateur neuf",
  "Vous montez une poulie roue libre neuve à l'aide de l'outil de dépose spécifique",
  4),

Q("Fac2o278", SPEC_PL, SCEN,
  "Sur l'alternateur d'un porteur HINO, déposé à l'atelier pour réfection, les balais sont en butée et le régulateur est marqué. Quelle intervention réalisez-vous ?",
  "Vous remplacez l'ensemble régulateur / porte-balais par le kit constructeur",
  "Vous ressoudez les balais existants sur leurs ressorts",
  "Vous remplacez le pont de diodes et laissez les balais en place",
  "Vous réalésez les bagues du rotor pour rattraper le jeu",
  1),

Q("Fac2o279", SPEC_PL, SCEN,
  "L'alternateur neuf est remonté sur un HINO, mais le voyant de charge reste allumé contact mis. Avant de déposer à nouveau l'organe, quelle vérification de remontage effectuez-vous ?",
  "Vous remplacez le combiné de bord",
  "Vous augmentez la tension de la courroie",
  "Vous rebranchez et verrouillez le connecteur de commande (D+/W) et la borne B+, souvent oubliés au remontage",
  "Vous remplacez les batteries",
  3),

// ---------------------------------------------------------------------
// BLOC 3 — Circuit de demarrage (7)
// ---------------------------------------------------------------------
Q("Fac2o280", SPEC_PL, SCEN,
  "Le démarreur d'un SINOTRUK HOWO est à remplacer et le véhicule est sur fosse. Dans quel ordre menez-vous la dépose ?",
  "Fixations, câble de puissance, fil de commande, batteries déconnectées en fin d'opération",
  "Batteries déconnectées, câble de puissance, fil de commande du solénoïde, puis les fixations",
  "Fil de commande, fixations, câble de puissance, batteries laissées branchées",
  "Batteries déconnectées, puis les fixations en premier pour dégager l'accès",
  2),

Q("Fac2o281", SPEC_PL, SCEN,
  "Vous engagez le démarreur neuf sur un HINO. Que contrôlez-vous avant de serrer les fixations ?",
  "Le sens de rotation du pignon à vide",
  "L'application d'un cordon de pâte à joint sur toute la portée",
  "La propreté du plan d'appui et la présence des cales d'origine, dont dépend l'engrènement pignon / couronne",
  "L'épaisseur du joint torique de l'axe de fourchette",
  3),

Q("Fac2o282", SPEC_PL, SCEN,
  "Le démarreur neuf est reposé sur un HINO 500 mais le lancement reste mou, batteries chargées. Quelle action de reprise menez-vous en priorité ?",
  "Vous remplacez le contacteur à clé",
  "Vous déposez et remplacez à nouveau le démarreur",
  "Vous montez un fusible de commande de calibre supérieur",
  "Vous déposez, nettoyez et resserrez les câbles de masse batteries / châssis / moteur",
  4),

Q("Fac2o283", SPEC_PL, SCEN,
  "Sur un FUSO, le câble de puissance du démarreur présente des brins coupés et un point de chauffe près de la cosse. Quelle réparation retenez-vous ?",
  "Vous remplacez le câble complet, de section identique à l'origine, avec des cosses serties à l'outil adapté",
  "Vous raccordez les deux tronçons par un domino à vis puis isolez au ruban",
  "Vous ressoudez les brins à l'étain et gainez au ruban adhésif",
  "Vous doublez le câble existant par un second conducteur de section inférieure",
  1),

Q("Fac2o284", SPEC_PL, SCEN,
  "Vous terminez le remontage du démarreur sur un SINOTRUK HOWO : il reste le petit fil de commande de la borne 50 à raccorder. Que faites-vous ?",
  "Vous l'isolez, il n'est plus nécessaire sur un démarreur neuf",
  "Vous le raccordez sur la borne de puissance B+",
  "Vous le raccordez sur la borne de commande du solénoïde et remettez son capuchon de protection",
  "Vous le raccordez à la masse du bloc moteur",
  3),

Q("Fac2o285", SPEC_PL, SCEN,
  "Vous venez de remplacer le contacteur à clé d'un porteur. Quel contrôle réalisez-vous avant de rendre le véhicule ?",
  "Vous mesurez la résistance interne du barillet",
  "Vous essayez les positions accessoires / contact / démarrage et vérifiez le retour automatique du barillet après lancement",
  "Vous contrôlez uniquement l'allumage des voyants contact mis",
  "Vous reprogrammez le calculateur de boîte",
  2),

Q("Fac2o286", SPEC_PL, SCEN,
  "Le relais de commande du démarreur d'un Actros est collé. Le magasin vous propose trois références de même encombrement. Sur quel critère choisissez-vous ?",
  "Le calibre le plus élevé disponible, pour la marge de sécurité",
  "N'importe quelle référence 24 V, l'encombrement étant identique",
  "Une référence 12 V, la commande étant en basse tension",
  "Un relais de même calibre et même brochage, muni de la diode de roue libre si l'original en comporte une",
  4),

// ---------------------------------------------------------------------
// BLOC 4 — Batteries 24 V (7)
// ---------------------------------------------------------------------
Q("Fac2o287", SPEC_PL, SAVOIR,
  "Vous déposez les deux batteries 12 V d'un porteur 24 V pour remplacement. Avant de débrancher la liaison qui les relie entre elles, comment sont-elles montées ?",
  "En série : la borne + de l'une est reliée à la borne - de l'autre",
  "En parallèle : les + ensemble et les - ensemble",
  "En série-parallèle, via un boîtier de commutation",
  "Indépendamment, chacune alimentant la moitié du véhicule",
  1),

Q("Fac2o288", SPEC_PL, SCEN,
  "Sur un Renault Trucks, une seule des deux batteries est hors service. Le client demande de ne changer que celle-là. Que faites-vous ?",
  "Vous remplacez la seule batterie défectueuse, comme demandé",
  "Vous expliquez qu'il faut remplacer les deux ensemble, de même référence, capacité et âge, et le portez sur l'ordre de réparation",
  "Vous remplacez la défectueuse par un modèle de capacité supérieure",
  "Vous inversez les deux batteries pour équilibrer l'usure",
  2),

Q("Fac2o289", SPEC_PL, SCEN,
  "Les batteries neuves sont posées et bridées sur un HOWO. Vous sortez la graisse de protection. À quel moment l'appliquez-vous ?",
  "Sur les bornes avant de poser les cosses, pour faciliter le montage",
  "Jamais, la graisse étant proscrite sur une batterie",
  "Après avoir nettoyé et serré les cosses au couple, en protection extérieure du contact",
  "À l'intérieur du bac, en mélange avec un produit anticorrosion",
  3),

Q("Fac2o290", SPEC_PL, SCEN,
  "Un PL 24 V est en panne sur le parc, batteries à plat. Vous approchez un second PL pour le démarrage de secours. Comment branchez-vous les câbles ?",
  "+ sur + et - directement sur la borne - de la batterie déchargée",
  "+ sur - en croisant les deux véhicules",
  "Le seul câble positif, la masse passant par le contact des châssis",
  "+ sur +, puis la masse du véhicule en panne sur un point métallique nu éloigné des batteries",
  4),

Q("Fac2o291", SPEC_PL, SAVOIR,
  "Vous commandez les batteries de remplacement d'un tracteur routier et le magasin propose plusieurs capacités. Que retenez-vous ?",
  "Une capacité au moins égale à l'origine, en respectant le courant de démarrage préconisé",
  "La capacité la plus faible disponible, pour alléger le véhicule",
  "La moitié de la capacité d'origine, les deux batteries se complétant",
  "N'importe quelle capacité, seule la tension étant déterminante",
  1),

Q("Fac2o292", SPEC_PL, SCEN,
  "Les batteries d'un Mercedes Actros récent, doté d'une gestion électronique de l'énergie, viennent d'être remplacées. Quelle opération réalisez-vous avant restitution ?",
  "Vous faites rouler le véhicule 200 km avant de le rendre",
  "Vous déclarez les batteries neuves au calculateur de gestion d'énergie avec l'outil constructeur",
  "Vous débranchez le tachygraphe pendant 24 h",
  "Aucune, le système s'adaptant seul dès la première charge",
  2),

Q("Fac2o293", SPEC_PL, SCEN,
  "Vous déposez les batteries d'un porteur et constatez que le bac support est fortement corrodé par des coulures d'électrolyte. Quelle intervention effectuez-vous avant de remonter les batteries ?",
  "Vous remontez les batteries en l'état, la corrosion restant superficielle",
  "Vous peignez le bac par-dessus la corrosion",
  "Vous nettoyez et neutralisez la corrosion, traitez ou remplacez le bac, puis contrôlez le bridage",
  "Vous intercalez un carton entre le bac et les batteries",
  3),

// ---------------------------------------------------------------------
// BLOC 5 — Protections : fusibles et relais (7)
// ---------------------------------------------------------------------
Q("Fac2o294", SPEC_PL, SCEN,
  "Un fusible 30 A est grillé sur un HOWO immobilisé. Le magasin n'a que du 40 A et le client attend le véhicule. Que faites-vous ?",
  "Vous montez le 40 A, l'écart restant faible",
  "Vous montez le 40 A et signalez l'écart sur l'ordre de réparation",
  "Vous montez deux fusibles 15 A en parallèle",
  "Vous refusez le 40 A et commandez le bon calibre : un fusible surdimensionné laisse passer un courant que le faisceau ne supporte pas",
  4),

Q("Fac2o295", SPEC_PL, SAVOIR,
  "Vous remplacez le câble de puissance alternateur / batteries d'un tracteur routier et devez recommander la protection montée en ligne. De quel type est-elle ?",
  "Un fusible en verre de 5 A",
  "Un fusible à visser de forte intensité type MEGA, à remplacer par le même calibre",
  "Un fusible enfichable mini de 20 A",
  "Aucune protection, la liaison étant directe",
  2),

Q("Fac2o296", SPEC_PL, SCEN,
  "Vous remplacez un fusible sur un porteur et il grille immédiatement au remontage. Quelle action menez-vous ?",
  "Vous montez un fusible temporisé de même calibre",
  "Vous montez deux fusibles en série",
  "Vous ouvrez la protection du faisceau, localisez et supprimez le point de frottement à la masse, puis reposez un fusible neuf",
  "Vous shuntez le fusible pour libérer le véhicule",
  3),

Q("Fac2o297", SPEC_PL, SAVOIR,
  "Vous remplacez un relais 5 broches au brochage normalisé sur un Actros et vérifiez la correspondance des bornes avant de l'enficher. À quoi correspondent les bornes 85 et 86 ?",
  "À l'entrée et à la sortie du circuit de puissance",
  "Au contact travail et au contact repos",
  "À la masse châssis et au témoin de tableau de bord",
  "À la bobine de commande du relais",
  4),

Q("Fac2o298", SPEC_PL, SCEN,
  "Le relais de remplacement que vous sortez du magasin comporte une diode intégrée. Que faites-vous au montage ?",
  "Vous respectez la polarité repérée sur les bornes 85 / 86 : une diode montée à l'envers met la bobine en court-circuit",
  "Vous l'enfichez indifféremment, la diode fonctionnant dans les deux sens",
  "Vous dessoudez la diode avant montage",
  "Vous montez le relais bornes vers le haut pour refroidir la diode",
  1),

Q("Fac2o299", SPEC_PL, SCEN,
  "Un porteur rentre à l'atelier après un passage à gué, boîtier à fusibles noyé. Quelle intervention retenez-vous ?",
  "Vous soufflez à l'air comprimé et rendez le véhicule",
  "Vous remplacez uniquement les fusibles visiblement oxydés",
  "Vous percez le fond du boîtier pour évacuer l'eau",
  "Vous séchez ou remplacez le boîtier et les fusibles, puis protégez les contacts avec un produit adapté",
  4),

Q("Fac2o300", SPEC_PL, SCEN,
  "Vous remplacez un fusible à visser type MIDI sur la platine de puissance d'un tracteur. Quel geste conditionne la fiabilité de la réparation ?",
  "Orienter le fusible dans le sens du courant",
  "Ajouter une rondelle éventail sous chaque écrou",
  "Serrer les écrous au couple prescrit : un contact desserré chauffe et détruit le porte-fusible",
  "Choisir un fusible de la même marque que l'origine",
  3),

// ---------------------------------------------------------------------
// BLOC 6 — Faisceaux et connectique (10)
// ---------------------------------------------------------------------
Q("Fac2o301", SPEC_PL, SCEN,
  "Sur un Renault Trucks, le faisceau du feu arrière de remorque est arraché au niveau du connecteur. On vous confie la réparation. Quelle est la première opération avant de sertir le câble neuf ?",
  "Vous sertissez directement, la remorque étant dételée",
  "Vous débranchez l'attelage électrique et déposez la cosse négative, puis dégagez et repérez les fils",
  "Vous mettez le contact pour identifier les fils sous tension",
  "Vous coupez tous les fils au ras du connecteur pour repartir sur du propre",
  2),

Q("Fac2o302", SPEC_PL, SCEN,
  "Vous devez réaliser une épissure sur le faisceau châssis d'un Actros. Quelle méthode appliquez-vous ?",
  "Vous torsadez les brins et isolez au ruban adhésif",
  "Vous raccordez par domino à vis logé dans une boîte de dérivation",
  "Vous sertissez avec une pince à matrice adaptée, puis protégez par une gaine thermorétractable à colle",
  "Vous soudez à l'étain sans protection complémentaire",
  3),

Q("Fac2o303", SPEC_PL, SAVOIR,
  "Vous protégez l'épissure que vous venez de sertir sur le faisceau châssis d'un Actros. Le stock propose du ruban isolant et de la gaine thermorétractable à colle. Pourquoi retenez-vous la seconde ?",
  "Parce qu'elle assure l'étanchéité et empêche la remontée d'humidité par capillarité dans le câble",
  "Parce qu'elle reprend la couleur du faisceau d'origine",
  "Parce qu'elle augmente la section du conducteur",
  "Parce qu'elle dispense de sertir la liaison",
  1),

Q("Fac2o304", SPEC_PL, SAVOIR,
  "Vous remplacez un tronçon de câble arraché sur le faisceau d'un porteur. Le touret disponible propose plusieurs sections. Laquelle retenez-vous ?",
  "La plus petite disponible, pour faciliter le cheminement",
  "Une section identique à l'origine, ou immédiatement supérieure",
  "Systématiquement du 0,75 mm², quel que soit le circuit",
  "Une section moitié moindre, la longueur ajoutée restant courte",
  2),

Q("Fac2o305", SPEC_PL, SCEN,
  "Vous devez réaliser trois épissures dans la même zone du faisceau de cabine d'un porteur. Comment les implantez-vous ?",
  "Groupées au même endroit, pour faciliter un contrôle ultérieur",
  "Toutes à l'intérieur d'un même connecteur",
  "Décalées les unes par rapport aux autres, pour éviter une surépaisseur et un point dur",
  "Toutes au point le plus bas du faisceau",
  3),

Q("Fac2o306", SPEC_PL, SCEN,
  "Le faisceau du porteur est réparé et gainé. Comment le remettez-vous en place ?",
  "Libre, pour qu'il absorbe les vibrations",
  "Fixé au ruban adhésif sur la durite la plus proche",
  "Tendu au maximum entre ses deux points d'ancrage",
  "Refixé sur ses colliers et cheminements d'origine, à l'écart des parties chaudes et des pièces mobiles",
  4),

Q("Fac2o307", SPEC_PL, SCEN,
  "Vous venez de sertir une cosse sur le câble d'alimentation que vous remplacez sur un porteur FUSO. Comment validez-vous le sertissage avant de gainer ?",
  "Vous vérifiez que la cosse ne tourne pas sur le câble",
  "Vous contrôlez que la cosse tient le conducteur et l'isolant, sans brin coupé ni débordant, et faites un essai de traction",
  "Vous chauffez légèrement pour faire fondre l'isolant dans la cosse",
  "Vous reportez le contrôle à l'ohmmètre après remontage complet",
  2),

Q("Fac2o308", SPEC_PL, SCEN,
  "Vous reposez un connecteur étanche sur un porteur exploité en zone tropicale. Deux cavités du connecteur ne sont pas utilisées. Que faites-vous ?",
  "Vous supprimez les joints mono-fil qui gênent l'insertion des broches",
  "Vous remontez chaque joint mono-fil et obturez les cavités inutilisées avec des bouchons",
  "Vous remplacez les joints par une couche de graisse silicone",
  "Vous ne remontez les joints que sur les fils d'alimentation",
  2),

Q("Fac2o309", SPEC_PL, SCEN,
  "Vous constatez une broche déformée dans un connecteur multibroches d'un Actros. Quelle intervention menez-vous ?",
  "Vous écartez la broche à la pointe d'un tournevis pour restaurer le contact",
  "Vous injectez de la graisse conductrice dans la cavité",
  "Vous extrayez la broche avec l'outil de déverrouillage et la remplacez par une broche neuve sertie",
  "Vous remplacez l'ensemble du faisceau du véhicule",
  3),

Q("Fac2o310", SPEC_PL, SCEN,
  "Une coupure est à réparer sur la paire torsadée du réseau multiplexé d'un Actros. Comment réalisez-vous la réparation ?",
  "En séparant les deux fils sur une dizaine de centimètres pour travailler à l'aise",
  "En remplaçant la paire torsadée par deux fils parallèles de même section",
  "En ajoutant une résistance de 120 ohms au point de réparation",
  "En reconstituant le pas de torsade sur toute la zone, sans décaler les épissures des deux conducteurs",
  4),

// ---------------------------------------------------------------------
// BLOC 7 — Eclairage et signalisation (8)
// ---------------------------------------------------------------------
Q("Fac2o311", SPEC_PL, SCEN,
  "Vous remplacez un feu arrière à ampoules par un feu à LED sur un porteur équipé du contrôle de lampes. Le tableau de bord signale aussitôt une lampe défectueuse. Quelle intervention retenez-vous ?",
  "Vous montez un feu LED compatible contrôle de lampes, ou ajoutez la résistance de charge préconisée",
  "Vous neutralisez le contrôle de lampes en déposant son fusible",
  "Vous remontez des ampoules à incandescence et refusez la LED",
  "Vous doublez le feu LED par un second feu identique",
  1),

Q("Fac2o312", SPEC_PL, SCEN,
  "Vous venez de remplacer le projecteur avant droit d'un tracteur routier. Quelle opération réalisez-vous avant restitution ?",
  "Un rodage de 30 minutes en feux de croisement",
  "Un réglage du faisceau au réglophare, selon les valeurs constructeur",
  "Le remplacement simultané du projecteur opposé",
  "Une reprogrammation du calculateur d'éclairage",
  2),

Q("Fac2o313", SPEC_PL, SCEN,
  "Vous sortez du magasin l'ampoule halogène H7 destinée au projecteur avant d'un tracteur routier. Comment la manipulez-vous ?",
  "Vous la faites chauffer avant montage pour éviter le choc thermique",
  "Vous la nettoyez à l'alcool une fois montée",
  "Vous évitez de toucher le verre à mains nues et essuyez toute trace de doigt avant montage",
  "Vous la montez en forçant légèrement pour assurer le contact",
  3),

Q("Fac2o314", SPEC_PL, SAVOIR,
  "Sur un ensemble tracteur / semi-remorque, les clignotants, feux stop et feux de position sont inopérants alors que les feux de recul fonctionnent. Sur quelle prise portez-vous votre intervention ?",
  "La prise ISO 7638",
  "La prise 24S (ISO 3731)",
  "La prise de diagnostic du tracteur",
  "La prise 24N (ISO 1185)",
  4),

Q("Fac2o315", SPEC_PL, SAVOIR,
  "Vous remplacez la prise 24S (ISO 3731) d'un tracteur routier. Quelles fonctions devez-vous retrouver à l'essai de remise en service ?",
  "Les feux de recul, l'antibrouillard arrière et les circuits complémentaires",
  "L'alimentation de l'ABS / EBS de la remorque",
  "Les clignotants et les feux stop",
  "Le réseau CAN de freinage",
  1),

Q("Fac2o316", SPEC_PL, SAVOIR,
  "Vous devez remplacer l'unique prise d'éclairage 15 broches d'un ensemble routier récent. De quelle prise s'agit-il et que reprend-elle ?",
  "De la prise ISO 7638 en version étendue, reprenant l'ABS et l'éclairage",
  "De la prise ISO 12098, qui regroupe les fonctions des prises 24N et 24S",
  "D'une prise propriétaire constructeur, hors norme",
  "De la prise 24S élargie, reprenant les feux stop",
  2),

Q("Fac2o317", SPEC_PL, SCEN,
  "La prise d'attelage électrique d'une semi-remorque est fortement oxydée, contacts piqués. Quelle intervention menez-vous ?",
  "Vous décapez les contacts à la toile émeri et remontez en l'état",
  "Vous injectez du dégrippant dans la prise",
  "Vous remplacez la prise et appliquez une graisse de contact adaptée sur les broches neuves",
  "Vous pontez les contacts oxydés par l'extérieur de la prise",
  3),

Q("Fac2o318", SPEC_PL, SCEN,
  "La réparation de l'éclairage d'un ensemble routier est terminée. Quel essai réalisez-vous avant restitution ?",
  "Un essai des seuls feux réparés, tracteur seul",
  "Un contrôle à l'ohmmètre des circuits déposés",
  "Une lecture des mémoires de défaut, suffisante à ce stade",
  "Un essai complet de toutes les fonctions d'éclairage et de signalisation, tracteur seul puis remorque attelée",
  4),

// ---------------------------------------------------------------------
// BLOC 8 — Attelage electrique ISO 7638 / EBS remorque (6)
// ---------------------------------------------------------------------
Q("Fac2o319", SPEC_PL, SAVOIR,
  "Un HINO 500 entre pour le remplacement d'une prise d'attelage arrachée. Le magasinier vous demande de préciser laquelle : celle de l'ABS / EBS ou celle de l'éclairage. Quelle prise assure l'alimentation et le dialogue de l'ABS / EBS remorque ?",
  "La prise ISO 7638",
  "La prise 24N (ISO 1185)",
  "La prise ISO 12098",
  "La prise de diagnostic du tracteur",
  1),

Q("Fac2o320", SPEC_PL, SAVOIR,
  "Vous commandez la prise ISO 7638 de remplacement d'un HINO 500. Combien de broches devez-vous préciser au magasinier ?",
  "5 broches",
  "7 broches",
  "13 broches",
  "15 broches",
  2),

Q("Fac2o321", SPEC_PL, SAVOIR,
  "Vous recâblez la prise ISO 7638-1 en 24 V d'un HINO 500. Deux conducteurs sont en 4 mm². À quelles broches les raccordez-vous, et pour quelle fonction ?",
  "Aux broches 6 et 7, pour le CAN H et le CAN L",
  "Aux broches 2 et 3, pour l'alimentation de l'électronique",
  "Aux broches 1 et 4, pour l'alimentation positive et négative des électrovannes",
  "Aux broches 5 et 1, pour le témoin et l'éclairage",
  3),

Q("Fac2o322", SPEC_PL, SAVOIR,
  "Vous recâblez la prise ISO 7638 d'un tracteur routier attelé à une remorque à EBS : il reste deux conducteurs en 1,5 mm² à raccorder aux broches 6 et 7. Quelle fonction assurent-ils ?",
  "Les alimentations de secours des électrovannes",
  "La masse électronique et la masse de puissance",
  "Le signal du capteur d'usure de garnitures",
  "Les lignes CAN H et CAN L du dialogue tracteur / remorque",
  4),

Q("Fac2o323", SPEC_PL, SAVOIR,
  "Vous terminez le recâblage de la prise ISO 7638 d'un tracteur routier : il reste la broche 5 à raccorder. À quelle fonction l'affectez-vous ?",
  "Au témoin d'avertissement ABS / EBS renvoyé au tableau de bord du tracteur",
  "À l'alimentation permanente du calculateur remorque",
  "À la masse générale de la remorque",
  "À l'alimentation du feu de recul",
  1),

Q("Fac2o324", SPEC_PL, SCEN,
  "Le câble spiralé ISO 7638 d'un ensemble est à remplacer. Le magasin vous propose un spiralé d'éclairage type 24N, disponible immédiatement. Que faites-vous ?",
  "Vous le montez, l'encombrement et la longueur étant identiques",
  "Vous le montez en doublant les conducteurs d'alimentation",
  "Vous le refusez et commandez un spiralé ISO 7638 : les sections d'alimentation des électrovannes ne seraient pas respectées",
  "Vous le montez en n'utilisant que les broches d'alimentation",
  3),

// ---------------------------------------------------------------------
// BLOC 9 — Capteurs ABS / EBS : depose-repose (5)
// ---------------------------------------------------------------------
Q("Fac2o325", SPEC_PL, SCEN,
  "Vous remplacez un capteur de vitesse de roue ABS sur l'essieu arrière d'un porteur. Comment engagez-vous le capteur neuf dans sa douille ?",
  "Vous le reculez de 2 mm après contact avec la couronne",
  "Vous l'immobilisez à la goupille au milieu de la douille",
  "Vous laissez volontairement 1 mm de jeu à la pose",
  "Vous l'enfoncez à fond dans la douille, jusqu'au contact de la couronne dentée",
  4),

Q("Fac2o326", SPEC_PL, SAVOIR,
  "Sur l'essieu arrière d'un porteur, vous venez d'enfoncer le capteur ABS neuf à fond contre la couronne. L'apprenti qui vous assiste demande comment l'entrefer de fonctionnement va s'établir. Que lui répondez-vous ?",
  "Il se règle à la cale d'épaisseur avant serrage définitif",
  "Il s'établit seul : la couronne repousse le capteur dans sa douille aux premiers tours de roue",
  "Il se règle en tournant le capteur d'un quart de tour",
  "Il est fixé par une vis de réglage sur le support d'essieu",
  2),

Q("Fac2o327", SPEC_PL, SCEN,
  "Vous préparez la commande de pièces pour le remplacement d'un capteur de vitesse de roue ABS sur l'essieu d'un porteur. Quel élément ajoutez-vous systématiquement au capteur ?",
  "Le roulement de moyeu",
  "La couronne dentée",
  "La douille de serrage du capteur",
  "Le flexible de frein",
  3),

Q("Fac2o328", SPEC_PL, SCEN,
  "Vous appliquez la graisse préconisée avant d'engager le capteur ABS neuf sur l'essieu d'un porteur. Où la déposez-vous ?",
  "Sur le corps du capteur et l'intérieur de la douille, jamais sur la face de détection",
  "Sur la face de détection, pour préserver l'entrefer",
  "Sur les dents de la couronne",
  "Nulle part, aucune graisse n'étant admise sur ce montage",
  1),

Q("Fac2o329", SPEC_PL, SCEN,
  "Le capteur de vitesse de roue ABS est posé sur l'essieu arrière d'un porteur, il reste à remettre son câble en place. Comment procédez-vous ?",
  "Vous le tendez au maximum entre le capteur et le connecteur",
  "Vous le fixez au collier sur le flexible de frein",
  "Vous l'enroulez autour du support d'essieu",
  "Vous le refixez sur ses points d'origine avec une boucle de mou, à l'écart du tambour et des organes mobiles",
  4),

// ---------------------------------------------------------------------
// BLOC 10 — Remise en service et operations obligatoires apres reparation (6)
// ---------------------------------------------------------------------
Q("Fac2o330", SPEC_PL, SCEN,
  "Un calculateur vient d'être remplacé sur un Renault Trucks. Le véhicule est reposé au sol, prêt à partir. Quelle opération réalisez-vous avant restitution ?",
  "Vous faites rouler 100 km pour permettre l'auto-apprentissage",
  "Vous programmez et paramétrez le calculateur (VIN, configuration véhicule) avec l'outil constructeur",
  "Vous déconnectez les batteries pendant 30 minutes",
  "Aucune, un calculateur neuf étant livré configuré",
  2),

Q("Fac2o331", SPEC_PL, SCEN,
  "Le pare-brise portant la caméra d'aide à la conduite d'un tracteur routier vient d'être remplacé et la caméra a été reposée sur son support neuf. Quelle opération réalisez-vous ?",
  "Un effacement des défauts mémorisés",
  "Un nettoyage de l'optique de la caméra",
  "Le calibrage de la caméra, statique et/ou dynamique, selon la procédure constructeur",
  "Aucune, la caméra se réalignant au premier trajet",
  3),

Q("Fac2o332", SPEC_PL, SCEN,
  "Le radar du régulateur adaptatif a été déposé puis reposé lors d'une réparation de face avant sur un Actros. Sur quoi porte votre intervention avant restitution ?",
  "Sur la longueur du câble d'alimentation du radar",
  "Sur l'étanchéité du connecteur du radar",
  "Sur la propreté du cache de calandre",
  "Sur le réglage d'assiette du radar : quelques dixièmes de degré suffisent à fausser le système",
  4),

Q("Fac2o333", SPEC_PL, SCEN,
  "Vous devez remplacer le capteur de vitesse monté sur la boîte de vitesses d'un porteur, capteur relié au tachygraphe. Que prévoyez-vous à l'issue de l'intervention ?",
  "Un réétalonnage et un replombage du tachygraphe par un atelier agréé disposant d'une carte atelier",
  "La pose d'un plombage neuf pris au magasin",
  "Rien de particulier, le capteur étant une pièce mécanique",
  "Une simple mention sur le carnet d'entretien",
  1),

Q("Fac2o334", SPEC_PL, SAVOIR,
  "Un plombage de tachygraphe a été rompu pendant votre intervention et l'atelier agréé le plus proche est à 200 km. Dans quel délai doit-il être rétabli ?",
  "Au prochain contrôle technique du véhicule",
  "Sans délai, et au plus tard dans les 7 jours",
  "Dans les deux ans suivant l'intervention",
  "Aucun délai imposé tant que le véhicule reste sur site",
  2),

Q("Fac2o335", SPEC_PL, SCEN,
  "L'intervention électrique est terminée sur le véhicule du client, qui attend au comptoir. Quelle est votre dernière opération avant restitution ?",
  "Vous déconnectez les batteries pour préserver la charge",
  "Vous rendez le véhicule sans essai, la réparation ayant été contrôlée pièce par pièce",
  "Vous effacez les défauts mémorisés puis réalisez un essai complet des fonctions concernées par la réparation",
  "Vous effacez les défauts mémorisés, l'essai restant à la charge du client",
  3)

];


// =====================================================================
// ETAPE 1 — Insertion des 73 questions
// =====================================================================
print("=== ETAPE 1 : insertion de " + QUESTIONS.length + " questions ===");

var refs = QUESTIONS.map(function (q) { return q.ref; });
if (db.questions.find({ ref: { $in: refs } }).count() > 0) {
  print("!! ABANDON : des refs existent deja en base. Aucune insertion.");
  quit(1);
}
if (db.questions.find({ speciality: SPEC_PL }).count() > 0) {
  print("!! ABANDON : la specialite " + SPEC_PL + " existe deja. Aucune insertion.");
  quit(1);
}
db.questions.insertMany(QUESTIONS);
print("OK : " + db.questions.find({ ref: { $in: refs } }).count() + " questions inserees en '" + SPEC_PL + "'.");

var nScen = db.questions.find({ ref: { $in: refs }, orientation: "scenario_reparation" }).count();
print("  scenario_reparation : " + nScen + " (" + Math.round(nScen / QUESTIONS.length * 100) + "%, cible 70-80%)");
print("  savoir_ancre        : " + (QUESTIONS.length - nScen));

// =====================================================================
// ETAPE 2 — Creation du quiz de la nouvelle specialite
// =====================================================================
// Le triplet (speciality, type, level) reste UNIQUE : "Electricite Poids
// Lourds" n'existe pas encore, donc aucun doublon n'est cree.
print("\n=== ETAPE 2 : creation du quiz ===");

if (db.quizzes.find({ speciality: SPEC_PL, type: "Factuel", level: "Senior" }).count() > 0) {
  print("!! quiz deja present, creation ignoree.");
} else {
  var qIds = db.questions.find({ ref: { $in: refs } }, { _id: 1 }).toArray()
               .map(function (d) { return d._id; });
  db.quizzes.insertOne({
    label: SPEC_PL,
    speciality: SPEC_PL,
    type: "Factuel",
    level: "Senior",
    questions: qIds,
    total: qIds.length,
    active: true,
    created: CREATED
  });
  print("OK : quiz '" + SPEC_PL + " / Factuel / Senior' cree avec " + qIds.length + " questions.");
}

// Controle d'invariant : le triplet doit rester unique sur toute la collection
var seen = {}, dup = 0;
db.quizzes.find({}, { speciality: 1, type: 1, level: 1 }).forEach(function (q) {
  var k = q.speciality + "|" + q.type + "|" + q.level;
  if (seen[k]) { dup++; print("  !! DOUBLON DE TRIPLET : " + k); }
  seen[k] = 1;
});
print("  invariant (speciality, type, level) unique : " + (dup === 0 ? "OK" : dup + " doublon(s)"));

// =====================================================================
// ETAPE 3 — Rattachement aux 5 marques camions
// =====================================================================
print("\n=== ETAPE 3 : rattachement aux vehicles PL ===");

var quiz = db.quizzes.findOne({ speciality: SPEC_PL, type: "Factuel", level: "Senior" });
var res = db.vehicles.updateMany(
  { brand: { $in: BRANDS_PL }, type: "Factuel", level: "Senior" },
  { $addToSet: { quizzes: quiz._id } }
);
print("vehicles mis a jour : " + res.modifiedCount + " (attendu : " + BRANDS_PL.length + ")");

BRANDS_PL.forEach(function (b) {
  var v = db.vehicles.findOne({ brand: b, type: "Factuel", level: "Senior" });
  print("  " + b + " : " + (v ? v.quizzes.length : 0) + " quiz rattaches");
});

// Controle de non-regression : aucune marque hors perimetre ne doit voir le quiz
var fuites = [];
db.vehicles.find({ quizzes: quiz._id }, { brand: 1 }).forEach(function (v) {
  if (BRANDS_PL.indexOf(v.brand) === -1) { fuites.push(v.brand); }
});
print("  marques hors perimetre exposees : " + (fuites.length === 0 ? "aucune" : fuites.join(", ")));

// =====================================================================
// RESTE A FAIRE — cote PHP (non automatisable ici)
// =====================================================================
// La specialite doit etre declaree dans la configuration de couverture,
// sinon elle ne remonte pas dans les dashboards techniciens :
//
//  1. views/define/configGF.php   (fichier vivant : require par coverage.php,
//     dashboardtech.php et controllers/TechnicianDashboardController.php)
//     - functionalGroupsByLevel['Senior'] : ajouter 'Electricité Poids Lourds'
//     - nonSupportedGroupsByBrand : l'ajouter pour les 16 marques hors des 5
//       camions. ATTENTION, l'operation n'est complete que si les 11 marques
//       SANS entree aujourd'hui en recoivent une (BYD, CITROEN, MERCEDES,
//       MITSUBISHI, PEUGEOT, SUZUKI, TOYOTA, TOYOTA FORFLIT, VOLKSWAGEN,
//       YAMAHA BATEAU, YAMAHA MOTO) en plus des 5 a completer (JCB, KING LONG,
//       LOVOL, TOYOTA BT, TOYOTA FORKLIFT).
//       Si l'ajout est partiel, la couverture attendue des marques oubliees
//       augmente d'un groupe et leur taux de couverture chute artificiellement.
//     - noter que views/define/configGF2.php utilise une AUTRE cle,
//       'nonSupportedGroupsByBrandAndLevel', et que views/configGF.php et
//       views/configGF2.php sont des copies dont les consommateurs
//       n'ont pas ete tracees.
//
//  2. scripts/create_mecanicien_pl_paper.php
//     - THEMES['electricite']['specialities'] : ajouter la specialite,
//       sans quoi l'epreuve de recrutement PL ne tirera jamais dedans.
//
// A verifier ensuite (listes litterales de specialites) :
//     views/define/configGF2.php, views/configGF.php, views/configGF2.php,
//     views/define/technicianDetails.php, views/explore/subsidiaryResult.php,
//     views/explore/userSubsidiaryResult.php, views/measure/userEvaluation.php
//
// ROLLBACK COMPLET :
//   var q = db.quizzes.findOne({speciality:"Electricité Poids Lourds"});
//   db.vehicles.updateMany({quizzes:q._id}, {$pull:{quizzes:q._id}});
//   db.quizzes.deleteOne({_id:q._id});
//   db.questions.deleteMany({speciality:"Electricité Poids Lourds"});
