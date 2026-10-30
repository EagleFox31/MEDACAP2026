// Index du gestionnaire d'erreurs (core/error_handler.php) : une entrée par empreinte, purge après 90 jours.
db.app_errors.createIndex({ fingerprint: 1 }, { unique: true });
db.app_errors.createIndex({ lastAt: 1 }, { expireAfterSeconds: 7776000 });
