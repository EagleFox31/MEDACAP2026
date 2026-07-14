<?php

/** Retourne les refs quiz d'un document MongoDB (BSONArray ou array PHP). */
function mongoQuizRefs($doc, string $field = 'quizzes'): array
{
    if (!$doc || !isset($doc[$field])) {
        return [];
    }
    $q = $doc[$field];
    if (is_array($q)) {
        return $q;
    }
    if ($q instanceof Traversable) {
        return iterator_to_array($q);
    }
    return [];
}

function mongoQuizCount($doc, string $field = 'quizzes'): int
{
    return count(mongoQuizRefs($doc, $field));
}

function addQuizzesToTest($testsCollection, $testId, array $quizRefs): void
{
    if (empty($quizRefs)) {
        return;
    }
    $testsCollection->updateOne(
        ['_id' => $testId],
        ['$addToSet' => ['quizzes' => ['$each' => $quizRefs]]]
    );
}

function setTestQuizzesFromBrands($testsCollection, $vehiclesCollection, $test): int
{
    $brands = (array)($test['brand'] ?? []);
    $type = $test['type'] ?? '';
    $level = $test['level'] ?? '';
    $merged = [];

    foreach ($brands as $brand) {
        $vehicle = $vehiclesCollection->findOne([
            'brand' => $brand,
            'type' => $type,
            'level' => $level,
            'active' => true,
        ]);
        foreach (mongoQuizRefs($vehicle) as $ref) {
            $merged[(string)$ref] = $ref;
        }
    }

    $quizList = array_values($merged);
    $testsCollection->updateOne(
        ['_id' => $test['_id']],
        ['$set' => ['quizzes' => $quizList, 'total' => count($quizList)]]
    );

    return count($quizList);
}
