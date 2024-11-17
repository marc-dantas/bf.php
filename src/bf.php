<?php

const MEMORY = 2000; # 2KB

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
        error(false, "syntax error: unmatched braces in program", $braces);
    }
    return $xs;
}

function run(array $program, bool $repl) {
    $mem = array_fill(0, MEMORY, 0);
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
                if ($cursor >= MEMORY) {
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

function program(array $argv): string | null {
    if (count($argv) < 2) {
        return null;
    }
    return $argv[1];
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

function repl() {
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
            run(parse($program), true);
        } elseif ($input === "X") {
            $program = "";
        } else {
            $program .= $input;
        }
    }
}

function main(array $argv) {
    $program = program($argv);
    if ($program == null) {
        repl();
    } else {
        $xs = read_to_string($program);
        // var_dump(parse($xs));
        run(parse($xs), false);
    }
}

if (php_sapi_name() !== 'cli') {
    error(false, "this program should run on cli", null);
}
main($argv);