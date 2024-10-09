<?php

foreach (['left', 'right'] as $side) {
    $file = "pics/serfservice-portal-header-grey-$side.svg";
    $out = "pics/serfservice-portal-header-grey-$side-auror.svg";
    $svg_content = file_get_contents($file);

    $mapping = [
        'rgb(237,237,237)' => "hsl(223deg, 31%, 80%)",
        'white'            => "rgb(254, 201, 92)",
        'rgb(130,130,130)' => "#2f3f64",
        'rgb(209,209,209)' => "hsl(223deg, 31%, 80%)",
        'rgb(169,169,169)' => "#7d97d8",
    ];

    foreach ($mapping as $color => $variable) {
        $svg_content = str_replace($color, "$variable", $svg_content);
    }

    file_put_contents($out, $svg_content);
}
