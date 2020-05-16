<?php

namespace LeoVie\C2Spark;

use Safe\Exceptions\StringsException;

class FuncDefTranspiler extends Transpiler
{
    /** @var array<string, string> **/
    public array $returnType;

    private string $functionHead;

    public function __construct(array $variables)
    {
        $this->variables = $variables;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getFunctionHead(): string
    {
        return $this->functionHead;
    }

    public function transpileFuncDecl(array $data): void
    {
        $this->transpile($data['args'], $data['_nodetype']);
        $this->returnType = $this->transpile($data['type'], $data['_nodetype'])['value'];
    }

    public function transpileFuncDef(array $data): array
    {
        $this->transpile($data['body'], $data['_nodetype']);
        $this->transpile($data['decl'], $data['_nodetype']);

        $this->functionHead = $this->transpileFunctionHead();
        $code = $this->functionHead;
        $code .= "is\n";

        foreach ($this->variables as $variable) {
            $code .= $variable['name'] . ': ' . $variable['type']['value']['value'] . ";\n";
        }

        $code .= "begin\n";
        $code = $this->appendCompounds($code);
        $code .= 'end ' . $this->functionName . ';';

        return ['value' => $code];
    }

    public function transpileFunctionHead(): string
    {
        $code = $this->determineSubprogramKeyword();
        $code .= " $this->functionName";
        if (!empty($this->inParameters)) {
            $code .= " (";
            $code = $this->concatInParameters($code);
            $code .= ")";
        }
        $code .= "\n";
        $code = $this->appendReturnIfExists($code);

        return $code;
    }

    private function determineSubprogramKeyword(): string
    {
        if ($this->isFunction()) {
            return 'function';
        }

        return 'procedure';
    }

    private function isFunction(): bool
    {
        return $this->returnType['value'] !== 'void';
    }

    private function concatInParameters(string $code): string
    {
        foreach ($this->inParameters as $key => $inParameter) {
            $code .= $inParameter['name'] . ' : in ' . $inParameter['type']['value'] . '; ';
        }
        $code = $this->removeTrailingSemicolon($code);

        return $code;
    }

    /**
     * @throws StringsException
     */
    private function removeTrailingSemicolon(string $code): string
    {
        if (\Safe\substr($code, strlen($code) - 2, 2) === '; ') {
            $code = \Safe\substr($code, 0, strlen($code) - 2);
        }

        return $code;
    }

    private function appendReturnIfExists(string $code): string
    {
        if ($this->isFunction()) {
            $code .= 'return ' . $this->returnType['value'] . "\n";
        }

        return $code;
    }

    private function appendCompounds(string $code): string
    {
        foreach ($this->compounds as $key => $compound) {
            $code .= '    ' . $compound . ";\n";
        }

        return $code;
    }
}