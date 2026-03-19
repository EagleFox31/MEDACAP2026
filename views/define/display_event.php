<?php
session_start();

if (!isset($_SESSION["id"])) {
    header("Location: ../../");
    exit();
} else {

   require_once "../../vendor/autoload.php";

   // Create connection
   $conn = new MongoDB\Client("mongodb://localhost:27017");
   
   // Connecting in database
   $academy = $conn->academy;
   
   // Connecting in collections
   $trainings = $academy->trainings;

   $calendata = $trainings->find([ "active" => true ])->toArray();

   $datas = [];
   foreach ($calendata as $calenda) {
       array_push($datas, [
           "id" => strval($calenda["_id"]),
           'code' => $calenda->code,
           'name' => $calenda->label,
           'location' => $calenda->places,
           'brand' => $calenda->brand,
           'type' => $calenda->type,
           'startDate' => $calenda->startDates,
           'endDate' => $calenda->endDates,
       ]);
   }

    echo json_encode($datas);
    ?>
<?php
} ?>
