<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL: TRANSLATE_FIELD_VALUE(column, 'field', :locale)
 * SQL: jsonb_extract_path_text(column, 'field', locale_value).
 *
 * Returns the translated text value for a given field and locale
 * from a jsonb translations column.
 */
final class TranslateFieldValue extends FunctionNode
{
    private mixed $column;
    private mixed $field;
    private mixed $locale;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->field = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->locale = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $walker): string
    {
        return \sprintf(
            'jsonb_extract_path_text(%s, %s, %s)',
            $walker->walkArithmeticPrimary($this->column),
            $walker->walkArithmeticPrimary($this->locale),
            $walker->walkStringPrimary($this->field),
        );
    }
}
