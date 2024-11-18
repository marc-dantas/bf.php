<?php

const DEFAULT_MEMORY = 2000; # 2KB

enum Op
{
    case Add;
    case Sub;
    case Input;
    case Output;
    case Forward;
    case Back;
    case Loop;
    case Endloop;
}

function error(bool $repl, string $message, int | null $pos) {
    if ($pos == null) {
        echo "bf: error: " . $message;
    } else {
        echo "bf: error at character position " . $pos . ": " . $message;
    }
    if (!$repl) {
        die(1);
    }
}

function parse(string $code): array {
    $xs = array();
    $braces = 0;
    foreach (str_split($code) as $c => $i) {
        switch ($i) {
            case ".": array_push($xs, [$c, Op::Output]); break;
            case ",": array_push($xs, [$c, Op::Input]); break;
            case "+": array_push($xs, [$c, Op::Add]); break;
            case "-": array_push($xs, [$c, Op::Sub]); break;
            case ">": array_push($xs, [$c, Op::Forward]); break;
            case "<": array_push($xs, [$c, Op::Back]); break;
            case "[": array_push($xs, [$c, Op::Loop]); $braces += 1; break;
            case "]": array_push($xs, [$c, Op::Endloop]); $braces -= 1; break;
        }
    }
    if ($braces != 0) {
        error(false, "syntax error: unmatched braces in program", null);
    }
    return $xs;
}

function run(array $program, bool $repl, int $capacity) {
    $mem = array_fill(0, $capacity, 0);
    $cursor = 0;
    for ($ip = 0; $ip < count($program); $ip++) {
        $pos = $program[$ip][0];
        $op = $program[$ip][1];
        
        switch ($op) {
            case Op::Add:
                $mem[$cursor]++;
                break;
            case Op::Sub:
                $mem[$cursor]--;
                break;
            case Op::Forward:
                $cursor++;
                if ($cursor >= $capacity) {
                    error($repl, "memory overflow", $pos);
                }
                break;
            case Op::Back:
                $cursor--;
                if ($cursor < 0) {
                    error($repl, "memory underflow", $pos);
                }
                break;
            case Op::Loop:
                if ($mem[$cursor] == 0) {
                    $loopCount = 1;
                    while ($loopCount > 0) {
                        $ip++;
                        if ($program[$ip][1] === Op::Loop) {
                            $loopCount++;
                        } elseif ($program[$ip][1] === Op::Endloop) {
                            $loopCount--;
                        }
                    }
                }
                break;
            case Op::Endloop:
                if ($mem[$cursor] != 0) {
                    $loopCount = 1;
                    while ($loopCount > 0) {
                        $ip--;
                        if ($program[$ip][1] === Op::Endloop) {
                            $loopCount++;
                        } elseif ($program[$ip][1] === Op::Loop) {
                            $loopCount--;
                        }
                    }
                }
                break;
            case Op::Input:
                $input = fgets(STDIN);
                $mem[$cursor] = ord($input[0] ?? "\0");
                break;
            case Op::Output:
                echo chr($mem[$cursor]);
                break;
        }
    }
}

class Arguments {
    public string | null $path;
    public int $memory;

    public function __construct(string | null $path, int $memory) {
        $this->path = $path;
        $this->memory = $memory;
    }
}

function parse_arguments(array $argv): Arguments | null {
    $path = null;
    $memory = DEFAULT_MEMORY;

    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === "-m") {
            if (!isset($argv[$i + 1])) {
                error(false, "missing value for -m flag", null);
            }
            if (!ctype_digit($argv[$i + 1])) {
                error(false, "-m flag value must be an integer", null);
            }

            $memory = (int)$argv[$i + 1];
            if ($memory < DEFAULT_MEMORY) {
                echo "bf: warning: memory capacity is set to less than 2KB. It may cause unexpected memory overflow\n";
            }
            $i++;
        } else {
            if ($path !== null) {
                error(false, "unrecognized positional argument $path", null);
            }
            $path = $argv[$i];
        }
    }

    return new Arguments($path, $memory);
}

function read_to_string(string $path): string {
    $a = fopen($path, "r");
    if ($a == false) {
        error(false, "couldn't open file " . $path . ".", null);
    }
    $size = filesize($path);
    $xs = fread($a, $size);
    if ($xs == false) {
        error(false, "couldn't read file " . $path . ".", null);
    }
    fclose($a);
    return $xs;
}

function repl(int $capacity) {
    $program = "";

    echo "bf repl\n";
    echo "write R and hit enter to run the code\n";
    echo "write X and to clear the program\n";
    echo "write Q and hit enter to quit\n\n";

    while (true) {
        echo "\n";
        $input = trim(fgets(STDIN));
        if ($input === "Q") {
            die;
        } elseif ($input === "R") {
            run(parse($program), true, $capacity);
        } elseif ($input === "X") {
            $program = "";
        } else {
            $program .= $input;
        }
    }
}

function main(array $argv) {
    $args = parse_arguments($argv);

    if ($args->path === null) {
        repl($args->memory);
    } else {
        $xs = read_to_string($args->path);
        run(parse($xs), false, $args->memory);
    }
}

if (php_sapi_name() !== 'cli') {
    error(false, "this program should run on cli", null);
}
main($argv);