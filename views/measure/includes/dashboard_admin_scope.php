<?php
/**
 * Périmètre dashboard admin filiale : département (Equipment vs Motors) + exclusion comptes démo.
 * PHP 7.3
 */

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

if (!function_exists('dashboardAdminIsGroupScope')) {
    function dashboardAdminIsGroupScope()
    {
        return in_array($_SESSION['profile'] ?? '', array('Super Admin', 'Directeur Groupe'), true);
    }
}

if (!function_exists('dashboardAdminShouldExcludeDemoUsers')) {
    function dashboardAdminShouldExcludeDemoUsers()
    {
        return !dashboardAdminIsGroupScope();
    }
}

if (!function_exists('dashboardAdminGetDepartmentClauses')) {
    /**
     * @param MongoDB\Collection $users
     * @return array<int, array<string, mixed>>
     */
    function dashboardAdminGetDepartmentClauses($users)
    {
        static $deptClauses = null;
        if ($deptClauses !== null) {
            return $deptClauses;
        }

        $deptClauses = array();
        if (!isset($_SESSION['id'])) {
            return $deptClauses;
        }

        if (dashboardAdminIsGroupScope()) {
            return $deptClauses;
        }

        try {
            $userDoc = $users->findOne(array('_id' => new ObjectId($_SESSION['id'])));
        } catch (Exception $e) {
            $userDoc = null;
        }

        $department = null;
        if ($userDoc) {
            $department = isset($userDoc['department']) ? (string) $userDoc['department'] : null;
        }
        if (($department === null || $department === '' || $department === 'null') && isset($_SESSION['department'])) {
            $department = (string) $_SESSION['department'];
        }

        if ($department && trim($department) !== '' && trim($department) !== 'null') {
            $parts = preg_split('/\\s*&\\s*/', $department);
            foreach ($parts as $dep) {
                $dep = trim($dep);
                if ($dep === '') {
                    continue;
                }
                $deptClauses[] = array(
                    'department' => new Regex('^' . preg_quote($dep, '/') . '$', 'i'),
                );
            }
        }

        return $deptClauses;
    }
}

if (!function_exists('dashboardAdminApplyDepartmentScope')) {
    /**
     * @param array<string, mixed> $query
     * @param MongoDB\Collection $users
     */
    function dashboardAdminApplyDepartmentScope(&$query, $users)
    {
        if (dashboardAdminIsGroupScope()) {
            return;
        }

        $deptClauses = dashboardAdminGetDepartmentClauses($users);
        if (!empty($deptClauses)) {
            dashboardAdminMergeQueryClause($query, array('$or' => $deptClauses));
            return;
        }

        if (isset($_SESSION['department']) && $_SESSION['department'] !== 'Equipment & Motors') {
            $query['department'] = $_SESSION['department'];
        }
    }
}

if (!function_exists('dashboardAdminDemoExcludeClause')) {
    /**
     * @return array<string, mixed>|null
     */
    function dashboardAdminDemoExcludeClause()
    {
        if (!dashboardAdminShouldExcludeDemoUsers()) {
            return null;
        }

        return array(
            '$and' => array(
                array('$or' => array(
                    array('loadtestTag' => array('$exists' => false)),
                    array('loadtestTag' => null),
                    array('loadtestTag' => ''),
                )),
                array('username' => array('$not' => new Regex('demo', 'i'))),
            ),
        );
    }
}

if (!function_exists('dashboardAdminMergeQueryClause')) {
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $clause
     */
    function dashboardAdminMergeQueryClause(&$query, $clause)
    {
        if ($clause === null || $clause === array()) {
            return;
        }

        if ($query === array()) {
            $query = $clause;
            return;
        }

        if (isset($query['$and'])) {
            $query['$and'][] = $clause;
            return;
        }

        $query = array('$and' => array($query, $clause));
    }
}

if (!function_exists('dashboardAdminApplyTechnicianScope')) {
    /**
     * Filtre standard techniciens / managers test + département + exclusion démo.
     *
     * @param array<string, mixed> $query
     * @param MongoDB\Collection $users
     */
    function dashboardAdminApplyTechnicianScope(&$query, $users)
    {
        $query['isCandidat'] = array('$ne' => true);
        dashboardAdminApplyDepartmentScope($query, $users);
        dashboardAdminMergeQueryClause($query, dashboardAdminDemoExcludeClause());
    }
}

if (!function_exists('dashboardAdminIsDemoUserDoc')) {
    function dashboardAdminIsDemoUserDoc($user)
    {
        if ($user instanceof MongoDB\Model\BSONDocument) {
            $user = $user->getArrayCopy();
        } elseif (!is_array($user)) {
            return false;
        }

        if (!empty($user['loadtestTag'])) {
            return true;
        }

        $username = strtolower(isset($user['username']) ? (string) $user['username'] : '');
        return strpos($username, 'demo') !== false;
    }
}
