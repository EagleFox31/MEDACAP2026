// Enregistre sur une épreuve les domaines demandés par le client (papers.domains),
// utilisés par l'export recrutement à la place des spécialités MEDACAP.
// Usage : mongosh <base> --eval 'var PAPER="<id>"' set_paper_domains.js
// Sans PAPER : traite toutes les épreuves configurées ci-dessous.
var CONFIGS = {
  // Recrutement Electromécanicien Engins Senior - Novembre 2026 (domaine porté par la ref FacEng-<code>-NN)
  '6aea0028d63c448e2a0da302': [
    { key: 'electricite', label: 'Électricité (machine)', codes: ['FacEng-ELT'], specs: ['Electricité Poids Lourds'] },
    { key: 'electronique', label: 'Électronique', codes: ['FacEng-ELN'], specs: ['Réseaux de Communication'] },
    { key: 'moteur', label: 'Moteur', codes: ['FacEng-MOT'], specs: ['Moteur Diesel', 'Moteur Thermique'] },
    { key: 'hydraulique', label: 'Hydraulique', codes: ['FacEng-HYD'], specs: ['Hydraulique'] },
    { key: 'transmission', label: 'Transmission', codes: ['FacEng-TRA'],
      specs: ['Boite de Vitesse', 'Boite de Vitesse Mécanique', 'Arbre de Transmission', 'Demi Arbre de Roue', 'Pont', 'Reducteur'] }
  ],
  // Recrutement Mécanicien PL - Novembre 2026 (thèmes de create_pl_recrutement_nov2026_papers.php)
  '6aea302b83f88c502a0b5d12': [
    { key: 'maintenance', label: 'Maintenance mécanique', codes: [],
      specs: ['Transversale', 'Moteur Thermique', 'Moteur Diesel', 'Freinage', 'Direction', 'Suspension', 'Suspension à Lame',
        'Pont', 'Hydraulique', 'Arbre de Transmission', 'Demi Arbre de Roue', 'Boite de Vitesse', 'Boite de Vitesse Mécanique',
        'Boite de Transfert', 'Reducteur', 'Climatisation', 'Assistance à la Conduite'] },
    { key: 'electricite', label: 'Électricité', codes: ['FacPL-ELT'], specs: ['Electricité et Electronique', 'Réseaux de Communication'] },
    { key: 'pneumatique', label: 'Pneumatique', codes: [], specs: ['Pneumatique', 'Freinage Pneumatique', 'Suspension Pneumatique'] }
  ]
};

var ids = (typeof PAPER !== 'undefined') ? [PAPER] : Object.keys(CONFIGS);
ids.forEach(function (id) {
  var DOMAINS = CONFIGS[id];
  if (!DOMAINS) { throw new Error('Épreuve non configurée : ' + id); }
  var paper = db.papers.findOne({ _id: ObjectId(id) });
  if (!paper) { print('[absent] ' + id); return; }

  var out = DOMAINS.map(function (d) { return { key: d.key, label: d.label, questions: [] }; });
  var unknown = [];
  db.questions.find({ _id: { $in: paper.questions } }, { ref: 1, speciality: 1 }).forEach(function (q) {
    var ref = q.ref || '';
    var i = DOMAINS.findIndex(function (d) { return d.codes.some(function (c) { return ref.indexOf(c + '-') === 0; }); });
    if (i < 0) { i = DOMAINS.findIndex(function (d) { return d.specs.indexOf(q.speciality) >= 0; }); }
    if (i < 0) { unknown.push(q._id + ' ' + q.speciality); return; }
    out[i].questions.push(q._id);
  });
  if (unknown.length) { throw new Error(paper.label + ' : questions sans domaine : ' + unknown.join(', ')); }

  db.papers.updateOne({ _id: paper._id }, { $set: { domains: out } });
  print(paper.label);
  out.forEach(function (d) { print('  ' + d.label + ' : ' + d.questions.length); });
});
