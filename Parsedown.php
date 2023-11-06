<?php

class Parsedown
{
    //==============================
    //props
    //==============================
    protected bool $breaksEnabled;
    protected bool $markupEscaped;
    protected bool $urlsLinked = true;
    protected bool $safeMode;
    protected bool $strictMode;
    protected array $safeLinksWhitelist = [
        'http://',
        'https://',
        'ftp://',
        'ftps://',
        'mailto:',
        'tel:',
        'data:image/png;base64,',
        'data:image/gif;base64,',
        'data:image/jpeg;base64,',
        'irc:',
        'ircs:',
        'git:',
        'ssh:',
        'news:',
        'steam:',
    ];

    protected array $BlockTypes = [
        '#' => ['Header'],
        '*' => ['Rule', 'List'],
        '+' => ['List'],
        '-' => ['SetextHeader', 'Table', 'Rule', 'List'],
        '0' => ['List'],
        '1' => ['List'],
        '2' => ['List'],
        '3' => ['List'],
        '4' => ['List'],
        '5' => ['List'],
        '6' => ['List'],
        '7' => ['List'],
        '8' => ['List'],
        '9' => ['List'],
        ':' => ['Table'],
        '<' => ['Comment', 'Markup'],
        '=' => ['SetextHeader'],
        '>' => ['Quote'],
        '[' => ['Reference'],
        '_' => ['Rule'],
        '`' => ['FencedCode'],
        '|' => ['Table'],
        '~' => ['FencedCode'],
    ];

    protected array $unmarkedBlockTypes = ['Code'];

    protected array $InlineTypes = [
        '!' => ['Image'],
        '&' => ['SpecialCharacter'],
        '*' => ['Emphasis'],
        ':' => ['Url'],
        '<' => ['UrlTag', 'EmailTag', 'Markup'],
        '[' => ['Link'],
        '_' => ['Emphasis'],
        '`' => ['Code'],
        '~' => ['Strikethrough'],
        '\\' => ['EscapeSequence'],
    ];

    protected string $inlineMarkerList = '!*_&[:<`~\\';

    private static array $instances = [];

    protected array $DefinitionData;

    protected array $specialCharacters = [
        '\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|', '~'
    ];

    protected array $StrongRegex = [
        '*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{2}(?![*])/s',
        '_' => '/^__((?:\\\\_|[^_]|_[^_]*+_)+?)__(?!_)/us',
    ];

    protected array $EmRegex = [
        '*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
        '_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
    ];

    protected string $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*+(?:\s*+=\s*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';

    protected array $voidElements = [
        'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source',
    ];

    protected array $textLevelElements = [
        'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
        'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
        'i', 'rp', 'del', 'code', 'strike', 'marquee',
        'q', 'rt', 'ins', 'font', 'strong',
        's', 'tt', 'kbd', 'mark',
        'u', 'xm', 'sub', 'nobr',
        'sup', 'ruby',
        'var', 'span',
        'wbr', 'time',
    ];

    //=============================
    //constante
    //=============================
    const version = '1.8.0-beta-7';

    //=============================
    //fonction metier
    //=============================

    public function text($text): string
    {
        $Elements = $this->textElements($text);

        # convert to markup
        # trim line breaks
        return trim($this->elements($Elements), "\n");
    }

    protected function textElements($text): array
    {
        # make sure no definitions are set
        $this->DefinitionData = [];

        # standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        # remove surrounding line breaks
        $text = trim($text, "\n");

        # split text into lines
        $lines = explode("\n", $text);

        # iterate through lines to identify blocks
        return $this->linesElements($lines);
    }

    //=============================
    //setter
    //=============================

    public function setBreaksEnabled(bool $breaksEnabled): Parsedown
    {
        $this->breaksEnabled = $breaksEnabled;

        return $this;
    }

    public function setMarkupEscaped(bool $markupEscaped): Parsedown
    {
        $this->markupEscaped = $markupEscaped;

        return $this;
    }

    public function setUrlsLinked($urlsLinked)
    {
        $this->urlsLinked = $urlsLinked;

        return $this;
    }

    public function setSafeMode($safeMode)
    {
        $this->safeMode = (bool)$safeMode;

        return $this;
    }

    public function setStrictMode($strictMode)
    {
        $this->strictMode = (bool)$strictMode;

        return $this;
    }

    protected function lines(array $lines): string
    {
        return $this->elements($this->linesElements($lines));
    }

    protected function linesElements(array $lines): array
    {
        $Elements = [];
        $CurrentBlock = null;

        foreach ($lines as $line) {
            if (chop($line) === '') {
                if (isset($CurrentBlock)) {
                    $CurrentBlock['interrupted'] = (isset($CurrentBlock['interrupted'])
                        ? $CurrentBlock['interrupted'] + 1 : 1
                    );
                }

                continue;
            }

            while (($beforeTab = strstr($line, "\t", true)) !== false) {
                $shortage = 4 - mb_strlen($beforeTab, 'utf-8') % 4;

                $line = $beforeTab
                    . str_repeat(' ', $shortage)
                    . substr($line, strlen($beforeTab) + 1);
            }

            $indent = strspn($line, ' ');

            $text = $indent > 0 ? substr($line, $indent) : $line;

            $Line = ['body' => $line, 'indent' => $indent, 'text' => $text];

            if (isset($CurrentBlock['continuable'])) {
                $methodName = 'block' . $CurrentBlock['type'] . 'Continue';
                $Block = $this->$methodName($Line, $CurrentBlock);

                if (isset($Block)) {
                    $CurrentBlock = $Block;

                    continue;
                } else {
                    if ($this->isBlockCompletable($CurrentBlock['type'])) {
                        $methodName = 'block' . $CurrentBlock['type'] . 'Complete';
                        $CurrentBlock = $this->$methodName($CurrentBlock);
                    }
                }
            }

            $marker = $text[0];

            $blockTypes = $this->unmarkedBlockTypes;

            if (isset($this->BlockTypes[$marker])) {
                foreach ($this->BlockTypes[$marker] as $blockType) {
                    $blockTypes [] = $blockType;
                }
            }

            foreach ($blockTypes as $blockType) {
                $Block = $this->{"block$blockType"}($Line, $CurrentBlock);

                if (isset($Block)) {
                    $Block['type'] = $blockType;

                    if (!isset($Block['identified'])) {
                        if (isset($CurrentBlock)) {
                            $Elements[] = $this->extractElement($CurrentBlock);
                        }

                        $Block['identified'] = true;
                    }

                    if ($this->isBlockContinuable($blockType)) {
                        $Block['continuable'] = true;
                    }

                    $CurrentBlock = $Block;

                    continue 2;
                }
            }

            if (isset($CurrentBlock) and $CurrentBlock['type'] === 'Paragraph') {
                $Block = $this->paragraphContinue($Line, $CurrentBlock);
            }

            if (isset($Block)) {
                $CurrentBlock = $Block;
            } else {
                if (isset($CurrentBlock)) {
                    $Elements[] = $this->extractElement($CurrentBlock);
                }

                $CurrentBlock = $this->paragraph($Line);

                $CurrentBlock['identified'] = true;
            }
        }

        if (isset($CurrentBlock['continuable']) and $this->isBlockCompletable($CurrentBlock['type'])) {
            $methodName = 'block' . $CurrentBlock['type'] . 'Complete';
            $CurrentBlock = $this->$methodName($CurrentBlock);
        }

        if (isset($CurrentBlock)) {
            $Elements[] = $this->extractElement($CurrentBlock);
        }

        return $Elements;
    }

    protected function extractElement(array $Component)
    {
        if (!isset($Component['element'])) {
            if (isset($Component['markup'])) {
                $Component['element'] = ['rawHtml' => $Component['markup']];
            } elseif (isset($Component['hidden'])) {
                $Component['element'] = [];
            }
        }

        return $Component['element'];
    }

    protected function isBlockContinuable($Type): bool
    {
        return method_exists($this, 'block' . $Type . 'Continue');
    }

    protected function isBlockCompletable($Type): bool
    {
        return method_exists($this, 'block' . $Type . 'Complete');
    }

    protected function blockCode($Line, $Block = null): ?array
    {
        if (isset($Block) and $Block['type'] === 'Paragraph' and !isset($Block['interrupted'])) {
            return null;
        }

        if ($Line['indent'] >= 4) {
            $text = substr($Line['body'], 4);

            return [
                'element' => [
                    'name' => 'pre',
                    'element' => [
                        'name' => 'code',
                        'text' => $text,
                    ],
                ],
            ];
        }else{
            return null;
        }
    }

    protected function blockCodeContinue($Line, $Block): ?array
    {
        if ($Line['indent'] >= 4) {
            if (isset($Block['interrupted'])) {
                $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

                unset($Block['interrupted']);
            }

            $Block['element']['element']['text'] .= "\n";

            $text = substr($Line['body'], 4);

            $Block['element']['element']['text'] .= $text;

            return $Block;
        }else{
            return null;
        }
    }

    protected function blockCodeComplete($Block)
    {
        return $Block;
    }

    protected function blockComment($Line): ?array
    {
        if ($this->markupEscaped or $this->safeMode) {
            return null;
        }

        if (str_starts_with($Line['text'], '<!--')) {
            $Block = [
                'element' => [
                    'rawHtml' => $Line['body'],
                    'autobreak' => true,
                ],
            ];

            if (str_contains($Line['text'], '-->')) {
                $Block['closed'] = true;
            }

            return $Block;
        }else{
            return null;
        }
    }

    protected function blockCommentContinue($Line, array $Block): ?array
    {
        if (isset($Block['closed'])) {
            return null;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        if (str_contains($Line['text'], '-->')) {
            $Block['closed'] = true;
        }

        return $Block;
    }

    protected function blockFencedCode($Line): ?array
    {
        $marker = $Line['text'][0];

        $openerLength = strspn($Line['text'], $marker);

        if ($openerLength < 3) {
            return null;
        }

        $infostring = trim(substr($Line['text'], $openerLength), "\t ");

        if (str_contains($infostring, '`')) {
            return null;
        }

        $Element = [
            'name' => 'code',
            'text' => '',
        ];

        if ($infostring !== '') {
            $language = substr($infostring, 0, strcspn($infostring, " \t\n\f\r"));

            $Element['attributes'] = ['class' => "language-$language"];
        }

        return [
            'char' => $marker,
            'openerLength' => $openerLength,
            'element' => [
                'name' => 'pre',
                'element' => $Element,
            ],
        ];
    }

    protected function blockFencedCodeContinue($Line, $Block): ?array
    {
        if (isset($Block['complete'])) {
            return null;
        }

        if (isset($Block['interrupted'])) {
            $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

            unset($Block['interrupted']);
        }

        if (($len = strspn($Line['text'], $Block['char'])) >= $Block['openerLength']
            and chop(substr($Line['text'], $len), ' ') === ''
        ) {
            $Block['element']['element']['text'] = substr($Block['element']['element']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['element']['text'] .= "\n" . $Line['body'];

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        return $Block;
    }

    protected function blockHeader($Line): ?array
    {
        $level = strspn($Line['text'], '#');

        if ($level > 6) {
            return null;
        }

        $text = trim($Line['text'], '#');

        if ($this->strictMode and isset($text[0]) and $text[0] !== ' ') {
            return null;
        }

        $text = trim($text, ' ');

        return [
            'element' => [
                'name' => 'h' . $level,
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $text,
                    'destination' => 'elements',
                ]
            ],
        ];
    }

    protected function blockList($Line, array $CurrentBlock = null): ?array
    {
        list($name, $pattern) = $Line['text'][0] <= '-' ? ['ul', '[*+-]'] : ['ol', '[0-9]{1,9}+[.\)]'];

        if (preg_match('/^(' . $pattern . '([ ]++|$))(.*+)/', $Line['text'], $matches)) {
            $contentIndent = strlen($matches[2]);

            if ($contentIndent >= 5) {
                $contentIndent -= 1;
                $matches[1] = substr($matches[1], 0, -$contentIndent);
                $matches[3] = str_repeat(' ', $contentIndent) . $matches[3];
            } elseif ($contentIndent === 0) {
                $matches[1] .= ' ';
            }

            $markerWithoutWhitespace = strstr($matches[1], ' ', true);
            $markerType = ($name === 'ul' ? $markerWithoutWhitespace : substr($markerWithoutWhitespace, -1));
            $Block = [
                'indent' => $Line['indent'],
                'pattern' => $pattern,
                'data' => [
                    'type' => $name,
                    'marker' => $matches[1],
                    'markerType' => $markerType,
                    'markerTypeRegex' => preg_quote($markerType, '/')
                ],
                'element' => [
                    'name' => $name,
                    'elements' => [],
                ],
            ];

            if ($name === 'ol') {
                $listStart = ltrim(strstr($matches[1], $Block['data']['markerType'], true), '0') ?: '0';

                if ($listStart !== '1') {
                    if (
                        isset($CurrentBlock)
                        and $CurrentBlock['type'] === 'Paragraph'
                        and !isset($CurrentBlock['interrupted'])
                    ) {
                        return null;
                    }

                    $Block['element']['attributes'] = ['start' => $listStart];
                }
            }

            $Block['li'] = [
                'name' => 'li',
                'handler' => [
                    'function' => 'li',
                    'argument' => !empty($matches[3]) ? [$matches[3]] : [],
                    'destination' => 'elements'
                ]
            ];

            $Block['element']['elements'] [] = &$Block['li'];

            return $Block;
        }else{
            return null;
        }
    }

    protected function blockListContinue($Line, array $Block): ?array
    {
        if (isset($Block['interrupted']) and empty($Block['li']['handler']['argument'])) {
            return null;
        }

        $requiredIndent = ($Block['indent'] + strlen($Block['data']['marker']));

        if ($Line['indent'] < $requiredIndent
            and (
                (
                    $Block['data']['type'] === 'ol'
                    and preg_match('/^[0-9]++' . $Block['data']['markerTypeRegex'] . '(?:[ ]++(.*)|$)/', $Line['text'], $matches)
                ) or (
                    $Block['data']['type'] === 'ul'
                    and preg_match('/^' . $Block['data']['markerTypeRegex'] . '(?:[ ]++(.*)|$)/', $Line['text'], $matches)
                )
            )
        ) {
            if (isset($Block['interrupted'])) {
                $Block['li']['handler']['argument'] [] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = $matches[1] ?? '';

            $Block['indent'] = $Line['indent'];

            $Block['li'] = [
                'name' => 'li',
                'handler' => [
                    'function' => 'li',
                    'argument' => [$text],
                    'destination' => 'elements'
                ]
            ];

            $Block['element']['elements'] [] = &$Block['li'];

            return $Block;
        } elseif ($Line['indent'] < $requiredIndent and $this->blockList($Line)) {
            return null;
        }

        if ($Line['text'][0] === '[' and $this->blockReference($Line)) {
            return $Block;
        }

        if ($Line['indent'] >= $requiredIndent) {
            if (isset($Block['interrupted'])) {
                $Block['li']['handler']['argument'] [] = '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], $requiredIndent);

            $Block['li']['handler']['argument'] [] = $text;

            return $Block;
        }

        if (!isset($Block['interrupted'])) {
            $text = preg_replace('/^[ ]{0,' . $requiredIndent . '}+/', '', $Line['body']);

            $Block['li']['handler']['argument'] [] = $text;

            return $Block;
        }else{
            return null;
        }
    }

    protected function blockListComplete(array $Block): array
    {
        if (isset($Block['loose'])) {
            foreach ($Block['element']['elements'] as &$li) {
                if (end($li['handler']['argument']) !== '') {
                    $li['handler']['argument'] [] = '';
                }
            }
        }

        return $Block;
    }

    protected function blockQuote($Line): ?array
    {
        if (preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches)) {
           return [
                'element' => [
                    'name' => 'blockquote',
                    'handler' => [
                        'function' => 'linesElements',
                        'argument' => (array)$matches[1],
                        'destination' => 'elements',
                    ]
                ],
            ];
        }else{
            return null;
        }
    }

    protected function blockQuoteContinue($Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) {
            return null;
        }

        if ($Line['text'][0] === '>' and preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches)) {
            $Block['element']['handler']['argument'] [] = $matches[1];

            return $Block;
        }
        $Block['element']['handler']['argument'] [] = $Line['text'];

        return $Block;
    }

    protected function blockRule($Line): ?array
    {
        $marker = $Line['text'][0];

        if (substr_count($Line['text'], $marker) >= 3 and chop($Line['text'], " $marker") === '') {
            return [
                'element' => [
                    'name' => 'hr',
                ],
            ];
        }else{
            return null;
        }
    }

    protected function blockSetextHeader($Line, array $Block = null): ?array
    {
        if (!isset($Block) or $Block['type'] !== 'Paragraph' or isset($Block['interrupted'])) {
            return null;
        }

        if ($Line['indent'] < 4 and chop(chop($Line['text'], ' '), $Line['text'][0]) === '') {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }else{
            return null;
        }
    }

    protected function blockMarkup($Line): ?array
    {
        if ($this->markupEscaped or $this->safeMode) {
            return null;
        }

        if (preg_match('/^<[\/]?+(\w*)(?:[ ]*+' . $this->regexHtmlAttribute . ')*+[ ]*+(\/)?>/', $Line['text'], $matches)) {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements)) {
                return null;
            }

            return [
                'name' => $matches[1],
                'element' => [
                    'rawHtml' => $Line['text'],
                    'autobreak' => true,
                ],
            ];
        }else{
            return null;
        }
    }

    protected function blockMarkupContinue($Line, array $Block): ?array
    {
        if (isset($Block['closed']) or isset($Block['interrupted'])) {
            return null;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        return $Block;
    }

    protected function blockReference($Line): ?array
    {
        if (str_contains($Line['text'], ']')
            and preg_match('/^\[(.+?)\]:[ ]*+<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*+$/', $Line['text'], $matches)
        ) {
            $id = strtolower($matches[1]);

            $this->DefinitionData['Reference'][$id] = [
                'url' => $matches[2],
                'title' => $matches[3] ?? null,
            ];

            return [
                'element' => [],
            ];
        }else{
            return null;
        }
    }

    protected function blockTable($Line, array $Block = null): ?array
    {
        if (!isset($Block) or $Block['type'] !== 'Paragraph' or isset($Block['interrupted'])) {
            return null;
        }

        if (
            !str_contains($Block['element']['handler']['argument'], '|')
            and !str_contains($Line['text'], '|')
            and !str_contains($Line['text'], ':')
            or str_contains($Block['element']['handler']['argument'], "\n")
        ) {
            return null;
        }

        if (chop($Line['text'], ' -:|') !== '') {
            return null;
        }

        $alignments = [];

        $divider = $Line['text'];

        $divider = trim($divider);
        $divider = trim($divider, '|');

        $dividerCells = explode('|', $divider);

        foreach ($dividerCells as $dividerCell) {
            $dividerCell = trim($dividerCell);

            if ($dividerCell === '') {
                return null;
            }

            $alignment = null;

            if ($dividerCell[0] === ':') {
                $alignment = 'left';
            }

            if (str_ends_with($dividerCell, ':')) {
                $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments [] = $alignment;
        }

        $HeaderElements = [];

        $header = $Block['element']['handler']['argument'];

        $header = trim($header);
        $header = trim($header, '|');

        $headerCells = explode('|', $header);

        if (count($headerCells) !== count($alignments)) {
            return null;
        }

        foreach ($headerCells as $index => $headerCell) {
            $headerCell = trim($headerCell);

            $HeaderElement = [
                'name' => 'th',
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $headerCell,
                    'destination' => 'elements',
                ]
            ];

            if (isset($alignments[$index])) {
                $alignment = $alignments[$index];

                $HeaderElement['attributes'] = [
                    'style' => "text-align: $alignment;",
                ];
            }

            $HeaderElements [] = $HeaderElement;
        }


        return [
            'alignments' => $alignments,
            'identified' => true,
            'element' => [
                'name' => 'table',
                'elements' => [
                    [
                        'name' => 'thead',
                        'elements'=>[
                            'name' => 'tr',
                            'elements' => $HeaderElements,
                        ]
                    ],
                    [
                        'name' => 'tbody',
                        'elements' => [],
                    ]
                ],
            ],
        ];
    }

    protected function blockTableContinue($Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) {
            return null;
        }

        if (count($Block['alignments']) === 1 or $Line['text'][0] === '|' or strpos($Line['text'], '|')) {
            $Elements = [];

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $row, $matches);

            $cells = array_slice($matches[0], 0, count($Block['alignments']));

            foreach ($cells as $index => $cell) {
                $cell = trim($cell);

                $Element = [
                    'name' => 'td',
                    'handler' => [
                        'function' => 'lineElements',
                        'argument' => $cell,
                        'destination' => 'elements',
                    ]
                ];

                if (isset($Block['alignments'][$index])) {
                    $Element['attributes'] = [
                        'style' => 'text-align: ' . $Block['alignments'][$index] . ';',
                    ];
                }

                $Elements [] = $Element;
            }

            $Element = [
                'name' => 'tr',
                'elements' => $Elements,
            ];

            $Block['element']['elements'][1]['elements'] [] = $Element;

            return $Block;
        }else{
            return null;
        }
    }

    protected function paragraph($Line): array
    {
        return [
            'type' => 'Paragraph',
            'element' => [
                'name' => 'p',
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $Line['text'],
                    'destination' => 'elements',
                ],
            ],
        ];
    }

    protected function paragraphContinue($Line, array $Block): ?array
    {
        if (isset($Block['interrupted'])) {
            return null;
        }

        $Block['element']['handler']['argument'] .= "\n" . $Line['text'];

        return $Block;
    }

    public function line($text, $nonNestables = array()): string
    {
        return $this->elements($this->lineElements($text, $nonNestables));
    }

    protected function lineElements($text, $nonNestables = array()): array
    {
        # standardize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $Elements = [];

        $nonNestables = empty($nonNestables)? [] : array_combine($nonNestables, $nonNestables);

        while ($excerpt = strpbrk($text, $this->inlineMarkerList)) {
            $marker = $excerpt[0];

            $markerPosition = strlen($text) - strlen($excerpt);

            $Excerpt = ['text' => $excerpt, 'context' => $text];

            foreach ($this->InlineTypes[$marker] as $inlineType) {
                # check to see if the current inline type is nestable in the current context

                if (isset($nonNestables[$inlineType])) {
                    continue;
                }

                $Inline = $this->{"inline$inlineType"}($Excerpt);

                if (!isset($Inline)) {
                    continue;
                }

                # makes sure that the inline belongs to "our" marker

                if (isset($Inline['position']) and $Inline['position'] > $markerPosition) {
                    continue;
                }

                # sets a default inline position

                if (!isset($Inline['position'])) {
                    $Inline['position'] = $markerPosition;
                }

                # cause the new element to 'inherit' our non nestables


                $Inline['element']['nonNestables'] = isset($Inline['element']['nonNestables'])
                    ? array_merge($Inline['element']['nonNestables'], $nonNestables)
                    : $nonNestables;

                # the text that comes before the inline
                $unmarkedText = substr($text, 0, $Inline['position']);

                # compile the unmarked text
                $InlineText = $this->inlineText($unmarkedText);
                $Elements[] = $InlineText['element'];

                # compile the inline
                $Elements[] = $this->extractElement($Inline);

                # remove the examined text
                $text = substr($text, $Inline['position'] + $Inline['extent']);

                continue 2;
            }

            # the marker does not belong to an inline

            $unmarkedText = substr($text, 0, $markerPosition + 1);

            $InlineText = $this->inlineText($unmarkedText);
            $Elements[] = $InlineText['element'];

            $text = substr($text, $markerPosition + 1);
        }

        $InlineText = $this->inlineText($text);
        $Elements[] = $InlineText['element'];

        foreach ($Elements as &$Element) {
            if (!isset($Element['autobreak'])) {
                $Element['autobreak'] = false;
            }
        }

        return $Elements;
    }

    protected function inlineText($text): array
    {
        $Inline = array(
            'extent' => strlen($text),
            'element' => array(),
        );

        $Inline['element']['elements'] = self::pregReplaceElements(
            $this->breaksEnabled ? '/[ ]*+\n/' : '/(?:[ ]*+\\\\|[ ]{2,}+)\n/',
            array(
                array('name' => 'br'),
                array('text' => "\n"),
            ),
            $text
        );

        return $Inline;
    }

    protected function inlineCode($Excerpt): ?array
    {
        $marker = $Excerpt['text'][0];

        if (preg_match('/^([' . $marker . ']++)[ ]*+(.+?)[ ]*+(?<![' . $marker . '])\1(?!' . $marker . ')/s', $Excerpt['text'], $matches)) {
            $text = $matches[2];
            $text = preg_replace('/[ ]*+\n/', ' ', $text);

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'code',
                    'text' => $text,
                ],
            ];
        }else{
            return null;
        }
    }

    protected function inlineEmailTag($Excerpt): ?array
    {
        $hostnameLabel = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

        $commonMarkEmail = '[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]++@'
            . $hostnameLabel . '(?:\.' . $hostnameLabel . ')*';

        if (str_contains($Excerpt['text'], '>')
            and preg_match("/^<((mailto:)?$commonMarkEmail)>/i", $Excerpt['text'], $matches)
        ) {
            $url = $matches[1];

            if (!isset($matches[2])) {
                $url = "mailto:$url";
            }

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $matches[1],
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }else{
            return null;
        }
    }

    protected function inlineEmphasis($Excerpt): ?array
    {
        if (!isset($Excerpt['text'][1])) {
            return null;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker and preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'strong';
        } elseif (preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'em';
        } else {
            return null;
        }

        return [
            'extent' => strlen($matches[0]),
            'element' => [
                'name' => $emphasis,
                'handler' => [
                    'function' => 'lineElements',
                    'argument' => $matches[1],
                    'destination' => 'elements',
                ]
            ],
        ];
    }

    protected function inlineEscapeSequence($Excerpt): ?array
    {
        if (isset($Excerpt['text'][1]) and in_array($Excerpt['text'][1], $this->specialCharacters)) {
            return [
                'element' => ['rawHtml' => $Excerpt['text'][1]],
                'extent' => 2,
            ];
        }else{
            return null;
        }
    }

    protected function inlineImage($Excerpt): ?array
    {
        if (!isset($Excerpt['text'][1]) or $Excerpt['text'][1] !== '[') {
            return null;
        }

        $Excerpt['text'] = substr($Excerpt['text'], 1);

        $Link = $this->inlineLink($Excerpt);

        if ($Link === null) {
            return null;
        }

        $Inline = [
            'extent' => $Link['extent'] + 1,
            'element' => [
                'name' => 'img',
                'attributes' => [
                    'src' => $Link['element']['attributes']['href'],
                    'alt' => $Link['element']['handler']['argument'],
                ],
                'autobreak' => true,
            ],
        ];

        $Inline['element']['attributes'] += $Link['element']['attributes'];

        unset($Inline['element']['attributes']['href']);

        return $Inline;
    }

    protected function inlineLink($Excerpt): ?array
    {
        $Element = [
            'name' => 'a',
            'handler' => [
                'function' => 'lineElements',
                'argument' => null,
                'destination' => 'elements',
            ],
            'nonNestables' => ['Url', 'Link'],
            'attributes' => [
                'href' => null,
                'title' => null,
            ],
        ];

        $extent = 0;

        $remainder = $Excerpt['text'];

        if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches)) {
            $Element['handler']['argument'] = $matches[1];

            $extent += strlen($matches[0]);

            $remainder = substr($remainder, $extent);
        } else {
            return null;
        }

        if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]/', $remainder, $matches)) {
            $Element['attributes']['href'] = $matches[1];

            if (isset($matches[2])) {
                $Element['attributes']['title'] = substr($matches[2], 1, -1);
            }

            $extent += strlen($matches[0]);
        } else {
            if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches)) {
                $definition = strlen($matches[1]) ? $matches[1] : $Element['handler']['argument'];
                $definition = strtolower($definition);

                $extent += strlen($matches[0]);
            } else {
                $definition = strtolower($Element['handler']['argument']);
            }

            if (!isset($this->DefinitionData['Reference'][$definition])) {
                return null;
            }

            $Definition = $this->DefinitionData['Reference'][$definition];

            $Element['attributes']['href'] = $Definition['url'];
            $Element['attributes']['title'] = $Definition['title'];
        }

        return [
            'extent' => $extent,
            'element' => $Element,
        ];
    }

    protected function inlineMarkup($Excerpt): ?array
    {
        if ($this->markupEscaped or $this->safeMode or !str_contains($Excerpt['text'], '>')) {
            return null;
        }

        if ($Excerpt['text'][1] === '/' and preg_match('/^<\/\w[\w-]*+[ ]*+>/s', $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent' => strlen($matches[0]),
            ];
        }

        if ($Excerpt['text'][1] === '!' and preg_match('/^<!---?[^>-](?:-?+[^-])*-->/s', $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent' => strlen($matches[0]),
            ];
        }

        if ($Excerpt['text'][1] !== ' ' and preg_match('/^<\w[\w-]*+(?:[ ]*+' . $this->regexHtmlAttribute . ')*+[ ]*+\/?>/s', $Excerpt['text'], $matches)) {
            return [
                'element' => ['rawHtml' => $matches[0]],
                'extent' => strlen($matches[0]),
            ];
        }
        return null;
    }

    protected function inlineSpecialCharacter($Excerpt): ?array
    {
        if (substr($Excerpt['text'], 1, 1) !== ' ' and str_contains($Excerpt['text'], ';')
            and preg_match('/^&(#?+[0-9a-zA-Z]++);/', $Excerpt['text'], $matches)
        ) {
            return [
                'element' => ['rawHtml' => '&' . $matches[1] . ';'],
                'extent' => strlen($matches[0]),
            ];
        }else{
            return null;
        }
    }

    protected function inlineStrikethrough($Excerpt): ?array
    {
        if (!isset($Excerpt['text'][1])) {
            return null;
        }

        if ($Excerpt['text'][1] === '~' and preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'del',
                    'handler' => [
                        'function' => 'lineElements',
                        'argument' => $matches[1],
                        'destination' => 'elements',
                    ]
                ],
            ];
        }else{
            return null;
        }
    }

    protected function inlineUrl($Excerpt): ?array
    {
        if ($this->urlsLinked !== true or !isset($Excerpt['text'][2]) or $Excerpt['text'][2] !== '/') {
            return null;
        }

        if (str_contains($Excerpt['context'], 'http')
            and preg_match('/\bhttps?+:[\/]{2}[^\s<]+\b\/*+/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)
        ) {
            $url = $matches[0][0];

            return [
                'extent' => strlen($matches[0][0]),
                'position' => $matches[0][1],
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }else{
            return null;
        }
    }

    protected function inlineUrlTag($Excerpt): ?array
    {
        if (str_contains($Excerpt['text'], '>') and preg_match('/^<(\w++:\/{2}[^ >]++)>/i', $Excerpt['text'], $matches)) {
            $url = $matches[1];

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'a',
                    'text' => $url,
                    'attributes' => [
                        'href' => $url,
                    ],
                ],
            ];
        }else{
            return null;
        }
    }

    protected function unmarkedText($text): string
    {
        $Inline = $this->inlineText($text);
        return $this->element($Inline['element']);
    }

    protected function handle(array $Element): array
    {
        if (isset($Element['handler'])) {
            if (!isset($Element['nonNestables'])) {
                $Element['nonNestables'] = [];
            }

            if (is_string($Element['handler'])) {
                $function = $Element['handler'];
                $argument = $Element['text'];
                unset($Element['text']);
                $destination = 'rawHtml';
            } else {
                $function = $Element['handler']['function'];
                $argument = $Element['handler']['argument'];
                $destination = $Element['handler']['destination'];
            }

            $Element[$destination] = $this->{$function}($argument, $Element['nonNestables']);

            if ($destination === 'handler') {
                $Element = $this->handle($Element);
            }

            unset($Element['handler']);
        }

        return $Element;
    }

    protected function handleElementRecursive(array $Element): mixed
    {
        return $this->elementApplyRecursive(array($this, 'handle'), $Element);
    }

    protected function handleElementsRecursive(array $Elements): array
    {
        return $this->elementsApplyRecursive(array($this, 'handle'), $Elements);
    }

    protected function elementApplyRecursive($closure, array $Element): mixed
    {
        $Element = call_user_func($closure, $Element);

        if (isset($Element['elements'])) {
            $Element['elements'] = $this->elementsApplyRecursive($closure, $Element['elements']);
        } elseif (isset($Element['element'])) {
            $Element['element'] = $this->elementApplyRecursive($closure, $Element['element']);
        }

        return $Element;
    }

    protected function elementApplyRecursiveDepthFirst($closure, array $Element): mixed
    {
        if (isset($Element['elements'])) {
            $Element['elements'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['elements']);
        } elseif (isset($Element['element'])) {
            $Element['element'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['element']);
        }

        return call_user_func($closure, $Element);
    }

    protected function elementsApplyRecursive($closure, array $Elements): array
    {
        foreach ($Elements as &$Element) {
            $Element = $this->elementApplyRecursive($closure, $Element);
        }

        return $Elements;
    }

    protected function elementsApplyRecursiveDepthFirst($closure, array $Elements): array
    {
        foreach ($Elements as &$Element) {
            $Element = $this->elementApplyRecursiveDepthFirst($closure, $Element);
        }

        return $Elements;
    }

    protected function element(array $Element): string
    {
        if ($this->safeMode) {
            $Element = $this->sanitiseElement($Element);
        }

        # identity map if element has no handler
        $Element = $this->handle($Element);

        $hasName = isset($Element['name']);

        $markup = '';

        if ($hasName) {
            $markup .= '<' . $Element['name'];

            if (isset($Element['attributes'])) {
                foreach ($Element['attributes'] as $name => $value) {
                    if ($value === null) {
                        continue;
                    }

                    $markup .= " $name=\"" . self::escape($value) . '"';
                }
            }
        }

        $permitRawHtml = false;

        if (isset($Element['text'])) {
            $text = $Element['text'];
        }
        // very strongly consider an alternative if you're writing an
        // extension
        elseif (isset($Element['rawHtml'])) {
            $text = $Element['rawHtml'];

            $allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
            $permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
        }

        $hasContent = isset($text) || isset($Element['element']) || isset($Element['elements']);

        if ($hasContent) {
            $markup .= $hasName ? '>' : '';

            if (isset($Element['elements'])) {
                $markup .= $this->elements($Element['elements']);
            } elseif (isset($Element['element'])) {
                $markup .= $this->element($Element['element']);
            } else {
                if (!$permitRawHtml) {
                    $markup .= self::escape($text, true);
                } else {
                    $markup .= $text;
                }
            }

            $markup .= $hasName ? '</' . $Element['name'] . '>' : '';
        } elseif ($hasName) {
            $markup .= ' />';
        }

        return $markup;
    }

    protected function elements(array $Elements): string
    {
        $markup = '';

        $autoBreak = true;

        foreach ($Elements as $Element) {
            if (empty($Element)) {
                continue;
            }

            $autoBreakNext = ($Element['autobreak'] ?? isset($Element['name'])
            );
            $autoBreak = !$autoBreak ? $autoBreak : $autoBreakNext;

            $markup .= ($autoBreak ? "\n" : '') . $this->element($Element);
            $autoBreak = $autoBreakNext;
        }

        $markup .= $autoBreak ? "\n" : '';

        return $markup;
    }

    protected function li($lines): array
    {
        $Elements = $this->linesElements($lines);

        if (!in_array('', $lines)
            and isset($Elements[0]) and isset($Elements[0]['name'])
            and $Elements[0]['name'] === 'p'
        ) {
            unset($Elements[0]['name']);
        }

        return $Elements;
    }

    protected static function pregReplaceElements($regexp, $Elements, $text): array
    {
        $newElements = [];

        while (preg_match($regexp, $text, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = (int) $matches[0][1];
            $before = substr($text, 0, $offset);
            $after = substr($text, $offset + strlen($matches[0][0]));

            $newElements[] = ['text' => $before];

            foreach ($Elements as $Element) {
                $newElements[] = $Element;
            }

            $text = $after;
        }

        $newElements[] = ['text' => $text];

        return $newElements;
    }

    public function parse($text): string
    {
        return $this->text($text);
    }

    protected function sanitiseElement(array $Element): array
    {
        static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
        static $safeUrlNameToAtt = [
            'a' => 'href',
            'img' => 'src',
        ];

        if (!isset($Element['name'])) {
            unset($Element['attributes']);
            return $Element;
        }

        if (isset($safeUrlNameToAtt[$Element['name']])) {
            $Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
        }

        if (!empty($Element['attributes'])) {
            foreach ($Element['attributes'] as $att => $val) {
                # filter out badly parsed attribute
                if (!preg_match($goodAttribute, $att)) {
                    unset($Element['attributes'][$att]);
                } # dump onevent attribute
                elseif (self::striAtStart($att, 'on')) {
                    unset($Element['attributes'][$att]);
                }
            }
        }

        return $Element;
    }

    protected function filterUnsafeUrlInAttribute(array $Element, $attribute): array
    {
        foreach ($this->safeLinksWhitelist as $scheme) {
            if (self::striAtStart($Element['attributes'][$attribute], $scheme)) {
                return $Element;
            }
        }

        $Element['attributes'][$attribute] = str_replace(':', '%3A', $Element['attributes'][$attribute]);

        return $Element;
    }

    protected static function escape($text, $allowQuotes = false): string
    {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function striAtStart($string, $needle): bool
    {
        $len = strlen($needle);

        if ($len > strlen($string)) {
            return false;
        } else {
            return strtolower(substr($string, 0, $len)) === strtolower($needle);
        }
    }

    public static function instance($name = 'default')
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $instance = new static();

        self::$instances[$name] = $instance;

        return $instance;
    }

}
