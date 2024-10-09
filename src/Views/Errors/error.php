<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error: <?= $errorcode ?></title>
    <style>
        * {
            box-sizing: border-box;
            padding: 0;
            margin: 0;
        }

        body {
            text-align: center;
            padding: 20px;
            background-color: darkslategray;
        }

        h1 {
            font-size: 5rem;
            padding: 0;
            margin: 0;
        }

        body {
            font: 20px Helvetica, sans-serif;
            color: #333;
        }

        .block {
            display: block;
            text-align: left;
            max-width: 850px;
            width: 100%;
            margin: 0 auto;
        }

        .errorheader {
            margin-top: 30px;
            margin-bottom: 20px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
        }

        .errorcode {
            color: red;
            text-shadow: #333 3px 3px 0px;
        }

        .subheading {
            color: cornsilk;
        }

        .code {
            font-size: 1rem;
            background-color: #333;
            padding: 10px;
            border-radius: 10px;
            color: forestgreen;
            margin-bottom: 20px;
            white-space: pre;
            text-wrap: wrap;
        }

        .tracelist {
            list-style-type: none;
            padding: 0;
        }

        .tracelist li {
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="errorheader">
        <h1 class="errorcode"><?= trim($errorcode) ?></h1>
        <small class="subheading"><?= $e->getMessage(); ?></small>
    </div>

    <?php
    $traceoutput = '';
    $backtrace = debug_backtrace();
    foreach ($backtrace as $index => $trace) {
        $traceoutput .= "<li>#" . $index . " " . $trace['file'] . " (" . $trace['line'] . "): ";
        $traceoutput .= isset($trace['class']) ? $trace['class'] . $trace['type'] : '';
        $traceoutput .= $trace['function'] . "()</li>";
    }
    ?>

    <?php
    if (!empty($args)) {
        $argsoutput = '';
        foreach ($args as $index => $arg) {
            $argsoutput .= print_r(value: $arg, return: true);
        }
    }
    ?>

    <?php if (isset($argsoutput)): ?>
        <div class="block code">
            <h3>Args data:</h3>
            <ul class="tracelist"><?= $argsoutput; ?></ul>
        </div>
    <?php endif; ?>

    <div class="block code">
        <h3>Stack trace:</h3>
        <ul class="tracelist"><?= $traceoutput; ?></ul>
    </div>
</body>

</html>