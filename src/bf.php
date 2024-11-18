<?php

const DEFAULT_MEMORY = 2000; # 2KB

class Op {
    public int $repeat;
    public OpKind $kind;
    public int $pos;

    public function __construct(int $repeat, OpKind $kind, int $pos) {
        $this->repeat = $repeat;
        $this->kind = $kind;
        $this->pos = $pos;
    }
}

enum OpKind {
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
    if ($pos === null) {
        echo "bf: error: " . $message . PHP_EOL;
    } else {
        echo "bf: error at character position " . $pos . ": " . $message . PHP_EOL;
    }
    if (!$repl) {
        die(1);
    }
}

function parse(string $code): array {
    $xs = [];
    $braces = 0;
    $length = strlen($code);
    $i = 0;
    while ($i < $length) {
        $char = $code[$i];
        $repeat = 1;
        switch ($char) {
            case '+':
            case '-':
            case '>':
            case '<':
                while ($i + 1 < $length && $code[$i + 1] === $char) {
                    $repeat++;
                    $i++;
                }
                $kind = match ($char) {
                    '+' => OpKind::Add,
                    '-' => OpKind::Sub,
                    '>' => OpKind::Forward,
                    '<' => OpKind::Back,
                };
                $xs[] = new Op($repeat, $kind, $i);
                break;
            case '.':
                $xs[] = new Op(1, OpKind::Output, $i);
                break;
            case ',':
                $xs[] = new Op(1, OpKind::Input, $i);
                break;
            case '[':
                $xs[] = new Op(1, OpKind::Loop, $i);
                $braces++;
                break;
            case ']':
                $xs[] = new Op(1, OpKind::Endloop, $i);
                $braces--;
                break;
        }
        $i++;
    }
    if ($braces !== 0) {
        error(false, "syntax error: unmatched braces in program", null);
    }
    return $xs;
}

function run(array $program, bool $repl, int $capacity) {
    $mem = array_fill(0, $capacity, 0);
    $cursor = 0;
    for ($ip = 0; $ip < count($program); $ip++) {
        $op = $program[$ip];
        $pos = $program[$ip]->pos;
        
        switch ($op->kind) {
            case OpKind::Add:
                $mem[$cursor] += $op->repeat;
                break;
            case OpKind::Sub:
                $mem[$cursor] -= $op->repeat;
                break;
            case OpKind::Forward:
                $cursor += $op->repeat;
                if ($cursor >= $capacity) {
                    error($repl, "memory overflow", $pos);
                }
                break;
            case OpKind::Back:
                $cursor -= $op->repeat;
                if ($cursor < 0) {
                    error($repl, "memory underflow", $pos);
                }
                break;
            case OpKind::Loop:
                if ($mem[$cursor] == 0) {
                    $loopCount = 1;
                    while ($loopCount > 0) {
                        $ip++;
                        if ($program[$ip]->kind === OpKind::Loop) {
                            $loopCount++;
                        } elseif ($program[$ip]->kind === OpKind::Endloop) {
                            $loopCount--;
                        }
                    }
                }
                break;
            case OpKind::Endloop:
                if ($mem[$cursor] != 0) {
                    $loopCount = 1;
                    while ($loopCount > 0) {
                        $ip--;
                        if ($program[$ip]->kind === OpKind::Endloop) {
                            $loopCount++;
                        } elseif ($program[$ip]->kind === OpKind::Loop) {
                            $loopCount--;
                        }
                    }
                }
                break;
            case OpKind::Input:
                $input = fread(STDIN, 1);
                $mem[$cursor] = ord($input ?? "\0");
                break;
            case OpKind::Output:
                echo chr($mem[$cursor]);
                break;
        }
    }
}

class Arguments {
    public string | null $path;
    public int $memory;
    public bool $debug;

    public function __construct(string | null $path, int $memory, bool $debug) {
        $this->path = $path;
        $this->memory = $memory;
        $this->debug = $debug;
    }
}

function parse_arguments(array $argv): Arguments | null {
    $path = null;
    $memory = DEFAULT_MEMORY;
    $debug = false;

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
        } elseif ($argv[$i] === "-debug") {
            $debug = true;
        } else {
            if ($path !== null) {
                error(false, "unrecognized positional argument $path", null);
            }
            $path = $argv[$i];
        }
    }

    return new Arguments($path, $memory, $debug);
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
        $input = trim(fgets(STDIN));
        if ($input === "Q") {
            echo "QUIT\n";
            die;
        } elseif ($input === "R") {
            echo "RUN\n";
            run(parse($program), true, $capacity);
            echo "\nEND\n\n";
        } elseif ($input === "X") {
            echo "CLEAR\n\n";
            $program = "";
        } else {
            $program .= $input;
        }
    }
}

function main(array $argv) {
    $args = parse_arguments($argv);

    if ($args->debug) {
        # debugger implementation
    }

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