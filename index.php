<?php
declare(strict_types=1);
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);

$config = [];
array_map(
    function (string $line) use (&$config) {
        $tmp = explode("=", $line);
        $config[trim($tmp[0])] = trim($tmp[1]);
    },
    file(__DIR__ . "/.env")
);

/**
 * @var $rawData array e.g. Array (
 *                            [0] => Array (
 *                              [0] => Server ID
 *                              [1] => Sponsor
 *                              [2] => Server Name
 *                              [3] => Timestamp
 *                              [4] => Distance
 *                              [5] => Ping
 *                              [6] => Download
 *                              [7] => Upload
 *                              [8] => Share
 *                              [9] => IP Address
 *                            )
 *                            [1] => Array (
 *                              [0] => 21966
 *                              [1] => WARIAN
 *                              [2] => Milano
 *                              [3] => 2020-08-31T15:40:11.737422Z
 *                              [4] => 46.29318715185764
 *                              [5] => 24.006
 *                              [6] => 25255591.181859758
 *                              [7] => 1658772.9046905963
 *                              [8] =>
 *                              [9] => 81.174.38.7
 *                            )
 *                            [2] => Array (
 *                              [0] => 21966
 *                              [1] => WARIAN
 *                              [2] => Milano
 *                              [3] => 2020-08-31T15:50:01.985005Z
 *                              [4] => 46.29318715185764
 *                              [5] => 25.675
 *                              [6] => 28355922.92406259
 *                              [7] => 1709195.4789188243
 *                              [8] =>
 *                              [9] => 81.174.38.7
 *                            )
 *                            [...]
 *                          )
 */
$raw = array_map("str_getcsv", file($config["RESULTS_FILE_PATH"]));

$rawHeaders = array_shift($raw);
$rawData = $raw;

function removeUselessFields(array $datum): array
{
    unset($datum[0]);
    unset($datum[1]);
    unset($datum[2]);
    unset($datum[4]);
    unset($datum[5]);
    unset($datum[8]);
    unset($datum[9]);
    $datum = array_values($datum);
    return $datum;
}

;
$rawHeaders = removeUselessFields($rawHeaders);
$rawData = array_map("removeUselessFields", $rawData);

/**
 * @var $headers array e.g. Array (
 *                            [0] => Date
 *                            [1] => Time
 *                            [2] => Download
 *                            [3] => Upload
 *                          )
 */
$rawHeaders[0] = "Time";
array_unshift($rawHeaders, "Date");
$headers = $rawHeaders;

/**
 * @var $data array e.g. Array (
 *                         [0] => Array (
 *                           [0] => 2020-08-31
 *                           [1] => 15:50
 *                           [2] => 28.36
 *                           [3] => 1.71
 *                         )
 *                         [1] => Array (
 *                           [0] => 2020-08-31
 *                           [1] => 15:40
 *                           [2] => 25.26
 *                           [3] => 1.66
 *                         )
 *                         [...]
 *                       )
 */
$rawData = array_map(
    function (array $datum): array {
        $datum[0] = preg_replace('/\..+$/', "", $datum[0]);
        $datum[1] = preg_replace('/\..+$/', "", $datum[1]);
        $datum[2] = preg_replace('/\..+$/', "", $datum[2]);
        $datum[1] = number_format((int)$datum[1] / 1000000, 2);
        $datum[2] = number_format((int)$datum[2] / 1000000, 2);
        $dateAndTime = explode("T", $datum[0]);
        $datum[0] = substr($dateAndTime[1], 0, 5);
        array_unshift($datum, $dateAndTime[0]);
        return $datum;
    },
    $rawData
);
$data = array_reverse($rawData);

$dates = array_values(array_unique(array_column($data, 0)));

?>

<!doctype html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Speed test viewer</title>
    <style>
        * {
            font-size: 36px;
            font-family: monospace;
        }

        td:nth-child(3),
        td:nth-child(4),
        th:nth-child(3),
        th:nth-child(4) {
            text-align: right;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js"></script>
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
          integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z"
          crossorigin="anonymous">
</head>

<body>

<canvas id="myChart" width="400" height="200"></canvas>
<script>
    new Chart(
        document.getElementById("myChart").getContext("2d"),
        {
            type: "bar",
            data: {
                labels: <?php echo json_encode(array_column($data, 1)); ?>,
                datasets: [{
                    label: "Download (Mbps)",
                    data: <?php echo json_encode(array_map("floatval", array_column($data, 2))); ?>
                }]
            },
            options: {
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        }
    );
</script>

<table class="table table-sm">

    <thead>
    <tr>
        <?php
        foreach ($headers as $field) {
            echo "<th scope=\"col\">$field</th>";
        }
        ?>
    </tr>
    </thead>

    <tbody>
    <?php
    foreach ($data as $datum) {
        echo "<tr>";
        foreach ($datum as $field) {
            echo "<td>$field</td>";
        }
        echo "</tr>";
    }
    ?>
    </tbody>

</table>

</body>
</html>