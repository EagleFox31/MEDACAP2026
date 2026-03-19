<?php
// includes/ModuleHelper.php

class ModuleHelper {
    /**
     * Obtient les modules actuels basés sur l'URL.
     *
     * @param MongoDB\Collection $functionalitiesCollection
     * @return array Les modules associés à la fonctionnalité actuelle.
     */
    public static function getCurrentModule($functionalitiesCollection) {
        // Obtenir l'URL actuelle sans les paramètres GET
        $currentUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Trouver la fonctionnalité correspondante
        $functionality = $functionalitiesCollection->findOne(['url' => $currentUrl, 'active' => true]);
        
        if ($functionality && isset($functionality['modules'])) {
            // Vérifier si 'modules' est un BSONArray et le convertir en tableau PHP
            if ($functionality['modules'] instanceof MongoDB\Model\BSONArray) {
                return $functionality['modules']->getArrayCopy();
            } elseif (is_array($functionality['modules'])) {
                return $functionality['modules'];
            } else {
                return [$functionality['modules']];
            }
        }
        
        return [];
    }
}
?>
