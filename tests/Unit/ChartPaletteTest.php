<?php

/**
 * @return array<string, array{lightness: float, chroma: float, hue: float}>
 */
function chartPaletteDeclarations(string $selector): array
{
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    if ($css === false) {
        throw new RuntimeException('Unable to read the application stylesheet.');
    }

    $selectorPattern = preg_quote($selector, '/');

    if (preg_match("/{$selectorPattern}\\s*\\{(?<declarations>.*?)\\}/s", $css, $block) !== 1) {
        throw new RuntimeException("Unable to find the {$selector} color declarations.");
    }

    preg_match_all(
        '/--(?<name>[a-z0-9-]+):\\s*oklch\\(\\s*(?<lightness>[0-9.]+)\\s+(?<chroma>[0-9.]+)\\s+(?<hue>[0-9.]+)\\s*\\)/',
        $block['declarations'],
        $matches,
        PREG_SET_ORDER,
    );

    $declarations = [];

    foreach ($matches as $match) {
        $declarations[$match['name']] = [
            'lightness' => (float) $match['lightness'],
            'chroma' => (float) $match['chroma'],
            'hue' => (float) $match['hue'],
        ];
    }

    return $declarations;
}

/**
 * @return array<string, array{lightness: float, chroma: float, hue: float}>
 */
function chartPaletteColors(string $selector): array
{
    $declarations = chartPaletteDeclarations($selector);
    $colors = [];

    foreach (range(1, 5) as $index) {
        $name = "chart-{$index}";

        if (! isset($declarations[$name])) {
            throw new RuntimeException("Missing --{$name} in {$selector}.");
        }

        $colors[$name] = $declarations[$name];
    }

    return $colors;
}

/**
 * @param  array{lightness: float, chroma: float, hue: float}  $color
 * @return array{lightness: float, a: float, b: float}
 */
function chartPaletteToOklab(array $color): array
{
    $hue = deg2rad($color['hue']);

    return [
        'lightness' => $color['lightness'],
        'a' => $color['chroma'] * cos($hue),
        'b' => $color['chroma'] * sin($hue),
    ];
}

/**
 * @param  array{lightness: float, chroma: float, hue: float}  $color
 * @return array{red: float, green: float, blue: float}
 */
function chartPaletteToLinearSrgb(array $color): array
{
    $oklab = chartPaletteToOklab($color);
    $lightness = $oklab['lightness'];
    $a = $oklab['a'];
    $b = $oklab['b'];

    $long = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $medium = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $short = ($lightness - 0.0894841775 * $a - 1.291485548 * $b) ** 3;

    return [
        'red' => 4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
        'green' => -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
        'blue' => -0.0041960863 * $long - 0.7034186147 * $medium + 1.707614701 * $short,
    ];
}

/**
 * @param  array{red: float, green: float, blue: float}  $color
 * @return array{lightness: float, a: float, b: float}
 */
function chartPaletteLinearSrgbToOklab(array $color): array
{
    $long = 0.4122214708 * $color['red'] + 0.5363325363 * $color['green'] + 0.0514459929 * $color['blue'];
    $medium = 0.2119034982 * $color['red'] + 0.6806995451 * $color['green'] + 0.1073969566 * $color['blue'];
    $short = 0.0883024619 * $color['red'] + 0.2817188376 * $color['green'] + 0.6299787005 * $color['blue'];

    $long = $long ** (1 / 3);
    $medium = $medium ** (1 / 3);
    $short = $short ** (1 / 3);

    return [
        'lightness' => 0.2104542553 * $long + 0.793617785 * $medium - 0.0040720468 * $short,
        'a' => 1.9779984951 * $long - 2.428592205 * $medium + 0.4505937099 * $short,
        'b' => 0.0259040371 * $long + 0.7827717662 * $medium - 0.808675766 * $short,
    ];
}

/**
 * @param  array{lightness: float, chroma: float, hue: float}  $color
 * @return array{lightness: float, a: float, b: float}
 */
function chartPaletteWithColorVisionDeficiency(array $color, string $deficiency): array
{
    $matrices = [
        'protanopia' => [
            [0.152286, 1.052583, -0.204868],
            [0.114503, 0.786281, 0.099216],
            [-0.003882, -0.048116, 1.051998],
        ],
        'deuteranopia' => [
            [0.367322, 0.860646, -0.227968],
            [0.280085, 0.672501, 0.047413],
            [-0.011820, 0.042940, 0.968881],
        ],
        'tritanopia' => [
            [1.255528, -0.076749, -0.178779],
            [-0.078411, 0.930809, 0.147602],
            [0.004733, 0.691367, 0.303900],
        ],
    ];

    if (! isset($matrices[$deficiency])) {
        throw new InvalidArgumentException("Unknown color vision deficiency: {$deficiency}.");
    }

    $rgb = chartPaletteToLinearSrgb($color);
    $channels = array_values($rgb);
    $simulated = [];

    foreach ($matrices[$deficiency] as $row) {
        $value = array_sum(array_map(
            fn (float $coefficient, float $channel): float => $coefficient * $channel,
            $row,
            $channels,
        ));
        $simulated[] = max(0, min(1, $value));
    }

    return chartPaletteLinearSrgbToOklab([
        'red' => $simulated[0],
        'green' => $simulated[1],
        'blue' => $simulated[2],
    ]);
}

/**
 * @param  array{lightness: float, a: float, b: float}  $first
 * @param  array{lightness: float, a: float, b: float}  $second
 */
function chartPaletteDeltaE(array $first, array $second): float
{
    return 100 * sqrt(
        ($first['lightness'] - $second['lightness']) ** 2
        + ($first['a'] - $second['a']) ** 2
        + ($first['b'] - $second['b']) ** 2,
    );
}

/**
 * @param  array{lightness: float, chroma: float, hue: float}  $first
 * @param  array{lightness: float, chroma: float, hue: float}  $second
 */
function chartPaletteContrastRatio(array $first, array $second): float
{
    $relativeLuminance = function (array $color): float {
        $rgb = chartPaletteToLinearSrgb($color);

        return 0.2126 * $rgb['red'] + 0.7152 * $rgb['green'] + 0.0722 * $rgb['blue'];
    };

    $luminances = [$relativeLuminance($first), $relativeLuminance($second)];
    rsort($luminances);

    return ($luminances[0] + 0.05) / ($luminances[1] + 0.05);
}

it('keeps every chart color in gamut and within the luminosity and chroma floors', function (
    string $selector,
    float $minimumLightness,
    float $maximumLightness,
) {
    $colors = chartPaletteColors($selector);
    $violations = [];

    foreach ($colors as $name => $color) {
        if ($color['lightness'] < $minimumLightness || $color['lightness'] > $maximumLightness) {
            $violations[] = "{$selector} --{$name} has lightness {$color['lightness']}";
        }

        if ($color['chroma'] < 0.1) {
            $violations[] = "{$selector} --{$name} has chroma {$color['chroma']}";
        }

        foreach (chartPaletteToLinearSrgb($color) as $channel => $value) {
            if ($value < 0 || $value > 1) {
                $violations[] = "{$selector} --{$name} is outside sRGB at {$channel}";
            }
        }
    }

    expect($violations)->toBeEmpty();
})->with([
    'light palette' => [':root', 0.4, 0.7],
    'dark palette' => ['.dark', 0.55, 0.85],
]);

it('separates adjacent chart colors by at least 15 delta E', function (string $selector) {
    $colors = array_values(chartPaletteColors($selector));
    $violations = [];

    for ($index = 0; $index < count($colors) - 1; $index++) {
        $deltaE = chartPaletteDeltaE(
            chartPaletteToOklab($colors[$index]),
            chartPaletteToOklab($colors[$index + 1]),
        );

        if ($deltaE < 15) {
            $first = $index + 1;
            $second = $index + 2;
            $violations[] = "{$selector} --chart-{$first}/--chart-{$second} has delta E {$deltaE}";
        }
    }

    expect($violations)->toBeEmpty();
})->with([
    'light palette' => ':root',
    'dark palette' => '.dark',
]);

it('keeps every chart color pair distinguishable with color vision deficiencies', function (string $selector) {
    $colors = chartPaletteColors($selector);
    $violations = [];

    foreach (['protanopia', 'deuteranopia', 'tritanopia'] as $deficiency) {
        foreach (array_keys($colors) as $firstIndex => $firstName) {
            foreach (array_slice(array_keys($colors), $firstIndex + 1) as $secondName) {
                $deltaE = chartPaletteDeltaE(
                    chartPaletteWithColorVisionDeficiency($colors[$firstName], $deficiency),
                    chartPaletteWithColorVisionDeficiency($colors[$secondName], $deficiency),
                );

                if ($deltaE < 10) {
                    $violations[] = "{$selector} --{$firstName}/--{$secondName} has {$deficiency} delta E {$deltaE}";
                }
            }
        }
    }

    expect($violations)->toBeEmpty();
})->with([
    'light palette' => ':root',
    'dark palette' => '.dark',
]);

it('maintains three-to-one contrast against its chart surface', function (string $selector) {
    $declarations = chartPaletteDeclarations($selector);
    $colors = chartPaletteColors($selector);
    $violations = [];

    foreach ($colors as $name => $color) {
        $contrast = chartPaletteContrastRatio($color, $declarations['background']);

        if ($contrast < 3) {
            $violations[] = "{$selector} --{$name} has contrast {$contrast}";
        }
    }

    expect($violations)->toBeEmpty();
})->with([
    'light palette' => ':root',
    'dark palette' => '.dark',
]);
