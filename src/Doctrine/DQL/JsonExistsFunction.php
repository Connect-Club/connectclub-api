<?php

namespace App\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class JsonExistsFunction extends FunctionNode
{
    public $leftHandSide = null;
    public $rightHandSide = null;

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->leftHandSide = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->rightHandSide = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'jsonb_exists_any(' .
            $this->rightHandSide->dispatch($sqlWalker) .'::jsonb, '.
            'ARRAY(
                SELECT jsonb_array_elements_text('.$this->leftHandSide->dispatch($sqlWalker).'::jsonb)
            )::text[])';
    }
}
