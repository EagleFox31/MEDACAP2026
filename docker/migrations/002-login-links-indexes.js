// Index de la connexion sans mot de passe (core/login_links.php).
// En prod, certains index existent déjà sous un autre nom (créés à la main) : on compare les clés, pas les noms.
function ensureIndex(coll, key, opts) {
  const wanted = JSON.stringify(key);
  // getIndexes() échoue sur une collection qui n'existe pas encore
  const present = db.getCollectionNames().indexOf(coll) !== -1 ? db.getCollection(coll).getIndexes() : [];
  const exists = present.some(function (i) { return JSON.stringify(i.key) === wanted; });
  if (!exists) {
    db.getCollection(coll).createIndex(key, opts);
  }
}
ensureIndex('login_links', { expiresAt: 1 }, { name: 'login_links_expiresAt', expireAfterSeconds: 0 });
ensureIndex('login_links', { tokenHash: 1 }, { name: 'login_links_tokenHash' });
ensureIndex('login_links', { user: 1, created: -1 }, { name: 'login_links_user_created' });
// Échecs de code : compteur sur 24 h par compte
ensureIndex('login_code_failures', { created: 1 }, { name: 'login_code_failures_ttl', expireAfterSeconds: 86400 });
ensureIndex('login_code_failures', { user: 1, created: 1 }, { name: 'login_code_failures_user' });
