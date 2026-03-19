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
   $calendars = $academy->calendars;

   $calendata = $calendars->find([ "active" => true ])->toArray();

   $datas = [];
   foreach ($calendata as $calenda) {
       array_push($datas, [
           "id" => strval($calenda["_id"]),
           'name' => $calenda->name,
           'description' => $calenda->description,
           'location' => $calenda->location,
           'startDate' => $calenda->startDate,
           'endDate' => $calenda->endDate,
           'startTime' => $calenda->startTime,
           'endTime' => $calenda->endTime,
           'allDay' => $calenda->allDay
       ]);
   }

    echo json_encode($datas);
    ?>
<?php
} ?>
