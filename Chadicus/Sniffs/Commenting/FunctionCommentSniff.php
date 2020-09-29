<?php

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\PEAR\Sniffs\Commenting\FunctionCommentSniff;
use PHP_CodeSniffer\Util\Tokens;
use PHP_CodeSniffer\Util\Common;

class Chadicus_Sniffs_Commenting_FunctionCommentSniff extends FunctionCommentSniff
{
    /**
     * The current PHP version.
     *
     * @var integer
     */
    private $_phpVersion = null;

    /**
     * An array of variable types for param/var we will check.
     *
     * @var array<string>
     */
    public static $allowedTypes = ['array', 'boolean', 'float', 'integer', 'mixed', 'object', 'string', 'resource', 'callable'];

    /**
     * Ensures only public methods are processed.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $methodProperties = $phpcsFile->getMethodProperties($stackPtr);
        if ($methodProperties['scope'] !== 'public') {
            return;
        }

        parent::process($phpcsFile, $stackPtr);
    }

    /**
     * Process the return comment of this function comment.
     *
     * @param File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processReturn(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Skip constructor and destructor.
        $methodName      = $phpcsFile->getDeclarationName($stackPtr);
        $isSpecialMethod = ($methodName === '__construct' || $methodName === '__destruct');

        $return = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                if ($return !== null) {
                    $error = 'Only 1 @return tag is allowed in a function comment';
                    $phpcsFile->addError($error, $tag, 'DuplicateReturn');
                    return;
                }

                $return = $tag;
            }
        }

        if ($isSpecialMethod === true) {
            return;
        }

        if ($return !== null) {
            $content = $tokens[($return + 2)]['content'];
            if (empty($content) === true || $tokens[($return + 2)]['code'] !== T_DOC_COMMENT_STRING) {
                $error = 'Return type missing for @return tag in function comment';
                $phpcsFile->addError($error, $return, 'MissingReturnType');
            } else {
                // Support both a return type and a description.
                $split = preg_match('`^((?:\|?(?:array\([^\)]*\)|[\\\\a-z0-9\[\]]+))*)( .*)?`i', $content, $returnParts);
                if (isset($returnParts[1]) === false) {
                    return;
                }

                $returnType = $returnParts[1];

                // Check return type (can be multiple, separated by '|').
                $typeNames      = explode('|', $returnType);
                $suggestedNames = array();
                foreach ($typeNames as $i => $typeName) {
                    $suggestedName = self::suggestType($typeName);
                    if (in_array($suggestedName, $suggestedNames) === false) {
                        $suggestedNames[] = $suggestedName;
                    }
                }

                $suggestedType = implode('|', $suggestedNames);
                if ($returnType !== $suggestedType) {
                    $error = 'Expected "%s" but found "%s" for function return type';
                    $data  = array(
                              $suggestedType,
                              $returnType,
                             );
                    $fix   = $phpcsFile->addFixableError($error, $return, 'InvalidReturn', $data);
                    if ($fix === true) {
                        $replacement = $suggestedType;
                        if (empty($returnParts[2]) === false) {
                            $replacement .= $returnParts[2];
                        }

                        $phpcsFile->fixer->replaceToken(($return + 2), $replacement);
                        unset($replacement);
                    }
                }

                // If the return type is void, make sure there is
                // no return statement in the function.
                if ($returnType === 'void') {
                    if (!$this->isReturnVoid($phpcsFile, $tokens, $stackPtr)) {
                        $error = 'Function return type is void, but function contains return statement';
                        $phpcsFile->addError($error, $return, 'InvalidReturnVoid');
                    }//end if
                } else if ($returnType !== 'mixed' && in_array('void', $typeNames, true) === false) {
                    // If return type is not void, there needs to be a return statement
                    // somewhere in the function that returns something.
                    if (isset($tokens[$stackPtr]['scope_closer']) === true) {
                        $endToken    = $tokens[$stackPtr]['scope_closer'];
                        $returnToken = $phpcsFile->findNext(array(T_RETURN, T_YIELD, T_YIELD_FROM), $stackPtr, $endToken);
                        if ($returnToken === false) {
                            $error = 'Function return type is not void, but function has no return statement';
                            $phpcsFile->addError($error, $return, 'InvalidNoReturn');
                        } else {
                            $semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
                            if ($tokens[$semicolon]['code'] === T_SEMICOLON) {
                                $error = 'Function return type is not void, but function is returning void here';
                                $phpcsFile->addError($error, $returnToken, 'InvalidReturnNotVoid');
                            }
                        }
                    }
                }//end if
            }//end if
        } else {
            if (!$this->isReturnVoid($phpcsFile, $tokens, $stackPtr)) {
                $error = 'Missing @return tag in function comment';
                $phpcsFile->addError($error, $tokens[$commentStart]['comment_closer'], 'MissingReturn');
            }
        }//end if

    }//end processReturn()

    private function isReturnVoid($phpcsFile, $tokens, $stackPtr) : bool
    {
        if (isset($tokens[$stackPtr]['scope_closer']) !== true) {
            return true;
        }

        $endToken = $tokens[$stackPtr]['scope_closer'];
        for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
            if ($tokens[$returnToken]['code'] === T_CLOSURE
                || $tokens[$returnToken]['code'] === T_ANON_CLASS
            ) {
                $returnToken = $tokens[$returnToken]['scope_closer'];
                continue;
            }

            if ($tokens[$returnToken]['code'] === T_RETURN
                || $tokens[$returnToken]['code'] === T_YIELD
                || $tokens[$returnToken]['code'] === T_YIELD_FROM
            ) {
                break;
            }
        }

        if ($returnToken === $endToken) {
            return true;
        }

        // If the function is not returning anything, just
        // exiting, then there is no problem.
        $semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
        if ($tokens[$semicolon]['code'] !== T_SEMICOLON) {
            return false;
        }

        return true;
    }

    /**
     * Process any throw tags that this function comment has.
     *
     * @param File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processThrows(File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $throws = array();
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@throws') {
                continue;
            }

            $exception = null;
            $comment   = null;
            if ($tokens[($tag + 2)]['code'] === T_DOC_COMMENT_STRING) {
                $matches = array();
                preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[($tag + 2)]['content'], $matches);
                $exception = $matches[1];
                if (isset($matches[2]) === true && trim($matches[2]) !== '') {
                    $comment = $matches[2];
                }
            }

            if ($exception === null) {
                $error = 'Exception type and comment missing for @throws tag in function comment';
                $phpcsFile->addError($error, $tag, 'InvalidThrows');
            } else if ($comment === null) {
                $error = 'Comment missing for @throws tag in function comment';
                $phpcsFile->addError($error, $tag, 'EmptyThrows');
            } else {
                // Any strings until the next tag belong to this comment.
                if (isset($tokens[$commentStart]['comment_tags'][($pos + 1)]) === true) {
                    $end = $tokens[$commentStart]['comment_tags'][($pos + 1)];
                } else {
                    $end = $tokens[$commentStart]['comment_closer'];
                }

                for ($i = ($tag + 3); $i < $end; $i++) {
                    if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                        $comment .= ' '.$tokens[$i]['content'];
                    }
                }
            }//end if
        }//end foreach

    }//end processThrows()

    /**
     * Process the function parameter comments.
     *
     * @param File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processParams(File $phpcsFile, $stackPtr, $commentStart)
    {
        if ($this->_phpVersion === null) {
            $this->_phpVersion = Config::getConfigData('php_version');
            if ($this->_phpVersion === null) {
                $this->_phpVersion = PHP_VERSION_ID;
            }
        }

        $tokens = $phpcsFile->getTokens();

        $params  = array();
        $maxType = 0;
        $maxVar  = 0;
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@param') {
                continue;
            }

            $type         = '';
            $typeSpace    = 0;
            $var          = '';
            $varSpace     = 0;
            $comment      = '';
            $commentLines = array();
            if ($tokens[($tag + 2)]['code'] === T_DOC_COMMENT_STRING) {
                $matches = array();
                preg_match('/([^$&.]+)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/', $tokens[($tag + 2)]['content'], $matches);

                if (empty($matches) === false) {
                    $typeLen   = strlen($matches[1]);
                    $type      = trim($matches[1]);
                    $typeSpace = ($typeLen - strlen($type));
                    $typeLen   = strlen($type);
                    if ($typeLen > $maxType) {
                        $maxType = $typeLen;
                    }
                }

                if (isset($matches[2]) === true) {
                    $var    = $matches[2];
                    $varLen = strlen($var);
                    if ($varLen > $maxVar) {
                        $maxVar = $varLen;
                    }

                    if (isset($matches[4]) === true) {
                        $varSpace       = strlen($matches[3]);
                        $comment        = $matches[4];
                        $commentLines[] = array(
                                           'comment' => $comment,
                                           'token'   => ($tag + 2),
                                           'indent'  => $varSpace,
                                          );

                        // Any strings until the next tag belong to this comment.
                        if (isset($tokens[$commentStart]['comment_tags'][($pos + 1)]) === true) {
                            $end = $tokens[$commentStart]['comment_tags'][($pos + 1)];
                        } else {
                            $end = $tokens[$commentStart]['comment_closer'];
                        }

                        for ($i = ($tag + 3); $i < $end; $i++) {
                            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                                $indent = 0;
                                if ($tokens[($i - 1)]['code'] === T_DOC_COMMENT_WHITESPACE) {
                                    $indent = strlen($tokens[($i - 1)]['content']);
                                }

                                $comment       .= ' '.$tokens[$i]['content'];
                                $commentLines[] = array(
                                                   'comment' => $tokens[$i]['content'],
                                                   'token'   => $i,
                                                   'indent'  => $indent,
                                                  );
                            }
                        }
                    } else {
                        $error = 'Missing parameter comment';
                        $phpcsFile->addError($error, $tag, 'MissingParamComment');
                        $commentLines[] = array('comment' => '');
                    }//end if
                } else {
                    $error = 'Missing parameter name';
                    $phpcsFile->addError($error, $tag, 'MissingParamName');
                }//end if
            } else {
                $error = 'Missing parameter type';
                $phpcsFile->addError($error, $tag, 'MissingParamType');
            }//end if

            $params[] = array(
                         'tag'          => $tag,
                         'type'         => $type,
                         'var'          => $var,
                         'comment'      => $comment,
                         'commentLines' => $commentLines,
                         'type_space'   => $typeSpace,
                         'var_space'    => $varSpace,
                        );
        }//end foreach

        $realParams  = $phpcsFile->getMethodParameters($stackPtr);
        $foundParams = array();

        // We want to use ... for all variable length arguments, so added
        // this prefix to the variable name so comparisons are easier.
        foreach ($realParams as $pos => $param) {
            if ($param['variable_length'] === true) {
                $realParams[$pos]['name'] = '...'.$realParams[$pos]['name'];
            }
        }

        foreach ($params as $pos => $param) {
            // If the type is empty, the whole line is empty.
            if ($param['type'] === '') {
                continue;
            }

            // Check the param type value.
            $typeNames          = explode('|', $param['type']);
            $suggestedTypeNames = array();

            foreach ($typeNames as $typeName) {
                $suggestedName        = self::suggestType($typeName);
                $suggestedTypeNames[] = $suggestedName;

                if (count($typeNames) > 1) {
                    continue;
                }

                // Check type hint for array and custom type.
                $suggestedTypeHint = '';
                if (strpos($suggestedName, 'array') !== false || substr($suggestedName, -2) === '[]') {
                    $suggestedTypeHint = 'array';
                } else if (strpos($suggestedName, 'callable') !== false) {
                    $suggestedTypeHint = 'callable';
                } else if (strpos($suggestedName, 'callback') !== false) {
                    $suggestedTypeHint = 'callable';
                } else if (in_array($suggestedName, Common::$allowedTypes) === false) {
                    $suggestedTypeHint = $suggestedName;
                }

                if ($this->_phpVersion >= 70000) {
                    if ($suggestedName === 'string') {
                        $suggestedTypeHint = 'string';
                    } else if ($suggestedName === 'int' || $suggestedName === 'integer') {
                        $suggestedTypeHint = 'int';
                    } else if ($suggestedName === 'float') {
                        $suggestedTypeHint = 'float';
                    } else if ($suggestedName === 'bool' || $suggestedName === 'boolean') {
                        $suggestedTypeHint = 'bool';
                    }
                }

                if ($suggestedTypeHint !== '' && isset($realParams[$pos]) === true) {
                    $typeHint = $realParams[$pos]['type_hint'];
                    if ($typeHint === '') {
                        $error = 'Type hint "%s" missing for %s';
                        $data  = array(
                                  $suggestedTypeHint,
                                  $param['var'],
                                 );

                        $errorCode = 'TypeHintMissing';
                        if ($suggestedTypeHint === 'string'
                            || $suggestedTypeHint === 'int'
                            || $suggestedTypeHint === 'float'
                            || $suggestedTypeHint === 'bool'
                        ) {
                            $errorCode = 'Scalar'.$errorCode;
                        }

                        $phpcsFile->addError($error, $stackPtr, $errorCode, $data);
                    } else if ($typeHint !== substr($suggestedTypeHint, (strlen($typeHint) * -1))) {
                        $error = 'Expected type hint "%s"; found "%s" for %s';
                        $data  = array(
                                  $suggestedTypeHint,
                                  $typeHint,
                                  $param['var'],
                                 );
                        $phpcsFile->addError($error, $stackPtr, 'IncorrectTypeHint', $data);
                    }//end if
                } else if ($suggestedTypeHint === '' && isset($realParams[$pos]) === true) {
                    $typeHint = $realParams[$pos]['type_hint'];
                    if ($typeHint !== '') {
                        $error = 'Unknown type hint "%s" found for %s';
                        $data  = array(
                                  $typeHint,
                                  $param['var'],
                                 );
                        $phpcsFile->addError($error, $stackPtr, 'InvalidTypeHint', $data);
                    }
                }//end if
            }//end foreach

            $suggestedType = implode('|', $suggestedTypeNames);
            if ($param['type'] !== $suggestedType) {
                $error = 'Expected "%s" but found "%s" for parameter type';
                $data  = array(
                          $suggestedType,
                          $param['type'],
                         );

                $fix = $phpcsFile->addFixableError($error, $param['tag'], 'IncorrectParamVarName', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->beginChangeset();

                    $content  = $suggestedType;
                    $content .= str_repeat(' ', $param['type_space']);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $param['var_space']);
                    if (isset($param['commentLines'][0]) === true) {
                        $content .= $param['commentLines'][0]['comment'];
                    }

                    $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                    // Fix up the indent of additional comment lines.
                    foreach ($param['commentLines'] as $lineNum => $line) {
                        if ($lineNum === 0
                            || $param['commentLines'][$lineNum]['indent'] === 0
                        ) {
                            continue;
                        }

                        $diff      = (strlen($param['type']) - strlen($suggestedType));
                        $newIndent = ($param['commentLines'][$lineNum]['indent'] - $diff);
                        $phpcsFile->fixer->replaceToken(
                            ($param['commentLines'][$lineNum]['token'] - 1),
                            str_repeat(' ', $newIndent)
                        );
                    }

                    $phpcsFile->fixer->endChangeset();
                }//end if
            }//end if

            if ($param['var'] === '') {
                continue;
            }

            $foundParams[] = $param['var'];

            // Check number of spaces after the type.
            $this->checkSpacingAfterParamType($phpcsFile, $param, $maxType);

            // Make sure the param name is correct.
            if (isset($realParams[$pos]) === true) {
                $realName = $realParams[$pos]['name'];
                if ($realName !== $param['var']) {
                    $code = 'ParamNameNoMatch';
                    $data = array(
                             $param['var'],
                             $realName,
                            );

                    $error = 'Doc comment for parameter %s does not match ';
                    if (strtolower($param['var']) === strtolower($realName)) {
                        $error .= 'case of ';
                        $code   = 'ParamNameNoCaseMatch';
                    }

                    $error .= 'actual variable name %s';

                    $phpcsFile->addError($error, $param['tag'], $code, $data);
                }
            } else if (substr($param['var'], -4) !== ',...') {
                // We must have an extra parameter comment.
                $error = 'Superfluous parameter comment';
                $phpcsFile->addError($error, $param['tag'], 'ExtraParamComment');
            }//end if

            if ($param['comment'] === '') {
                continue;
            }

            // Check number of spaces after the var name.
            $this->checkSpacingAfterParamName($phpcsFile, $param, $maxVar);
        }//end foreach

        $realNames = array();
        foreach ($realParams as $realParam) {
            $realNames[] = $realParam['name'];
        }

        // Report missing comments.
        $diff = array_diff($realNames, $foundParams);
        foreach ($diff as $neededParam) {
            $error = 'Doc comment for parameter "%s" missing';
            $data  = array($neededParam);
            $phpcsFile->addError($error, $commentStart, 'MissingParamTag', $data);
        }

    }//end processParams()

    /**
     * Check the spacing after the type of a parameter.
     *
     * @param File $phpcsFile The file being scanned.
     * @param array                $param     The parameter to be checked.
     * @param int                  $maxType   The maxlength of the longest parameter type.
     * @param int                  $spacing   The number of spaces to add after the type.
     *
     * @return void
     */
    protected function checkSpacingAfterParamType(File $phpcsFile, $param, $maxType, $spacing = 1)
    {
        // Check number of spaces after the type.
        $spaces = ($maxType - strlen($param['type']) + $spacing);
        if ($param['type_space'] !== $spaces) {
            $error = 'Expected %s spaces after parameter type; %s found';
            $data  = array(
                      $spaces,
                      $param['type_space'],
                     );

            $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamType', $data);
            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();

                $content  = $param['type'];
                $content .= str_repeat(' ', $spaces);
                $content .= $param['var'];
                $content .= str_repeat(' ', $param['var_space']);
                $content .= $param['commentLines'][0]['comment'];
                $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                // Fix up the indent of additional comment lines.
                foreach ($param['commentLines'] as $lineNum => $line) {
                    if ($lineNum === 0
                        || $param['commentLines'][$lineNum]['indent'] === 0
                    ) {
                        continue;
                    }

                    $diff      = ($param['type_space'] - $spaces);
                    $newIndent = ($param['commentLines'][$lineNum]['indent'] - $diff);
                    $phpcsFile->fixer->replaceToken(
                        ($param['commentLines'][$lineNum]['token'] - 1),
                        str_repeat(' ', $newIndent)
                    );
                }

                $phpcsFile->fixer->endChangeset();
            }//end if
        }//end if

    }//end checkSpacingAfterParamType()

    /**
     * Check the spacing after the name of a parameter.
     *
     * @param File $phpcsFile The file being scanned.
     * @param array                $param     The parameter to be checked.
     * @param int                  $maxVar    The maxlength of the longest parameter name.
     * @param int                  $spacing   The number of spaces to add after the type.
     *
     * @return void
     */
    protected function checkSpacingAfterParamName(File $phpcsFile, $param, $maxVar, $spacing = 1)
    {
        // Check number of spaces after the var name.
        $spaces = ($maxVar - strlen($param['var']) + $spacing);
        if ($param['var_space'] !== $spaces) {
            $error = 'Expected %s spaces after parameter name; %s found';
            $data  = array(
                      $spaces,
                      $param['var_space'],
                     );

            $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamName', $data);
            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();

                $content  = $param['type'];
                $content .= str_repeat(' ', $param['type_space']);
                $content .= $param['var'];
                $content .= str_repeat(' ', $spaces);
                $content .= $param['commentLines'][0]['comment'];
                $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                // Fix up the indent of additional comment lines.
                foreach ($param['commentLines'] as $lineNum => $line) {
                    if ($lineNum === 0
                        || $param['commentLines'][$lineNum]['indent'] === 0
                    ) {
                        continue;
                    }

                    $diff      = ($param['var_space'] - $spaces);
                    $newIndent = ($param['commentLines'][$lineNum]['indent'] - $diff);
                    $phpcsFile->fixer->replaceToken(
                        ($param['commentLines'][$lineNum]['token'] - 1),
                        str_repeat(' ', $newIndent)
                    );
                }

                $phpcsFile->fixer->endChangeset();
            }//end if
        }//end if

    }//end checkSpacingAfterParamName()

    /**
     * Returns a valid variable type for param/var tag.
     *
     * If type is not one of the standard type, it must be a custom type.
     * Returns the correct type name suggestion if type name is invalid.
     *
     * @param string $varType The variable type to process.
     *
     * @return string
     */
    public static function suggestType($varType)
    {
        if ($varType === '') {
            return '';
        }

        if (in_array($varType, self::$allowedTypes) === true) {
            return $varType;
        } else {
            $lowerVarType = strtolower($varType);
            switch ($lowerVarType) {
            case 'bool':
            case 'boolean':
                return 'bool';
            case 'double':
            case 'real':
            case 'float':
                return 'float';
            case 'int':
            case 'integer':
                return 'int';
            case 'array()':
            case 'array':
                return 'array';
            }//end switch

            if (strpos($lowerVarType, 'array(') !== false) {
                // Valid array declaration:
                // array, array(type), array(type1 => type2).
                $matches = array();
                $pattern = '/^array\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i';
                if (preg_match($pattern, $varType, $matches) !== 0) {
                    $type1 = '';
                    if (isset($matches[1]) === true) {
                        $type1 = $matches[1];
                    }

                    $type2 = '';
                    if (isset($matches[3]) === true) {
                        $type2 = $matches[3];
                    }

                    $type1 = self::suggestType($type1);
                    $type2 = self::suggestType($type2);
                    if ($type2 !== '') {
                        $type2 = ' => '.$type2;
                    }

                    return "array($type1$type2)";
                } else {
                    return 'array';
                }//end if
            } else if (in_array($lowerVarType, self::$allowedTypes) === true) {
                // A valid type, but not lower cased.
                return $lowerVarType;
            } else {
                // Must be a custom type name.
                return $varType;
            }//end if
        }//end if

    }//end suggestType()
}//end class
