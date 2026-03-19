<?php
/**
 * Gestion de la connexion à la base de données MongoDB
 * 
 * Note: Ce fichier utilise directement les classes MongoDB\Client 
 * car la classe MongoDB\BSON\ObjectId est déjà disponible via l'autoload
 */

/**
 * Établit une connexion à la base de données MongoDB
 *
 * @return MongoDB\Database Instance de la base de données
 */
function connectToDatabase() {
    try {
        // Récupération des paramètres de connexion depuis config.php
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $port = defined('DB_PORT') ? DB_PORT : '27017';
        $dbName = defined('DB_NAME') ? DB_NAME : 'academy';
        
        // Construction de l'URI de connexion
        $uri = "mongodb://{$host}:{$port}";
        
        // Création du client MongoDB et retour de la base de données
        $client = new MongoDB\Client($uri);
        return $client->$dbName;
        
    } catch (Exception $e) {
        // Log de l'erreur
        error_log("Erreur de connexion à MongoDB: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Effectue une requête MongoDB avec gestion des erreurs
 *
 * @param MongoDB\Collection $collection Collection MongoDB
 * @param array $filter Filtre de la requête
 * @param array $options Options de la requête
 * @return MongoDB\Driver\Cursor Résultat de la requête
 */
function executeMongoQuery($collection, $filter = [], $options = []) {
    try {
        return $collection->find($filter, $options);
    } catch (Exception $e) {
        error_log("Erreur lors de l'exécution de la requête MongoDB: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Effectue une agrégation MongoDB avec gestion des erreurs
 *
 * @param MongoDB\Collection $collection Collection MongoDB
 * @param array $pipeline Pipeline d'agrégation
 * @param array $options Options de l'agrégation
 * @return MongoDB\Driver\Cursor Résultat de l'agrégation
 */
function executeMongoAggregation($collection, $pipeline = [], $options = []) {
    try {
        return $collection->aggregate($pipeline, $options);
    } catch (Exception $e) {
        error_log("Erreur lors de l'exécution de l'agrégation MongoDB: " . $e->getMessage());
        throw $e;
    }
}