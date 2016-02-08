<?php
namespace vtvz\bparser;

use yii\base\Object;

/**
 * Парсер, поддерживающий фильтры и внутреннее кэширование.
 *
 * Чтобы запустить парсер разово, воспользуйтесь статическим методом [[process()]]:
 *
 * ~~~
 * echo Parser::process('{key-su}', ['key' => 'userItems']); // "user_item"
 * ~~~
 *
 * Если парсер будет исползоваться многоразово в одном месте, рекомендуется создать объект:
 *
 * ~~~
 * $parser = new Parser({
 *     'parts' => [
 *          'one' => 'First element',
 *          'many' => 'Few elements',
 *     ]
 * });
 * echo $parser->run('{one-u}_{many-vs} and {one}'); // "firts_element_fewElement and First element"
 * ~~~
 *
 * Фильтры настраиваются в свойстве [[$filters]]
 *
 * Данный парсер особенно полезен, когда используются различные шаблоны с одинаковыми данными
 *
 * @author Vitaly Zaslavsky <vtvz.ru@gmail.com>
 */
class Parser extends Object
{
    public $brackets = ['{', '}'];
    public $separator = '-';
    public $enableCache = true;

    public $filters = [
        'c' => ['yii\helpers\Inflector', 'camelize'],
        'u' => ['yii\helpers\Inflector', 'underscore'],
        'v' => ['yii\helpers\Inflector', 'variablize'],
        'p' => ['yii\helpers\Inflector', 'pluralize'],
        's' => ['yii\helpers\Inflector', 'singularize'],
        't' => 'trim',
        'l' => 'mb_strtolower',
        'U' => 'mb_strtoupper',
        'w' => 'ucwords',
        'f' => 'lcfirst',
        'F' => 'ucfirst',
    ];

    public $parts = [];

    public $cache = [];

    public function run($string)
    {
        if ($this->hasCache($string)) {
            return $this->getCache($string);
        }

        $leftBracket  = preg_quote($this->brackets[0], '~');
        $separator    = preg_quote($this->separator, '~');
        $filters      = implode(array_keys($this->filters));
        $rightBracket = preg_quote($this->brackets[1], '~');

        $regex = vsprintf('~%s([a-zA-Z0-9_]+)(%s([%s]+))?%s~', [
            $leftBracket,
            $separator,
            $filters,
            $rightBracket,
        ]);

        $result = preg_replace_callback($regex, __CLASS__ . '::processMatches', $string);

        $this->setCache($string, $result);

        return $result;
    }

    public static function process($string, $parts, $config = [])
    {
        $parserConfig = [
            'class' => static::className(),
            'parts' => $parts,
        ];

        $parserConfig = array_merge($parserConfig, $config);

        $parser = \Yii::createObject($parserConfig);

        return $parser->run($string);
    }

    private function processMatches($matches)
    {
        $fullMatch = $matches[0];

        //Достаем из кэша
        if ($this->hasCache($fullMatch)) {
            $value = $this->getCache($fullMatch);
            return $value;
        }

        $key = $matches[1];

        if (!key_exists($key, $this->parts)) {
            $this->setCache($fullMatch, $fullMatch);
            return $fullMatch;
        }

        $value = $this->parts[$key];

        if (key_exists(3, $matches)) {
            $value = $this->processFilters($value, $matches[3]);
        }

        $this->setCache($fullMatch, $value);

        return $value;
    }

    private function processFilters($value, $filters)
    {
        foreach (str_split($filters) as $filter) {
            if (key_exists($filter, $this->filters)) {
                $processor = $this->filters[$filter];

                $value = call_user_func($processor, $value);
            }
        }

        return $value;
    }

    private function setCache($key, $value)
    {
        if ($this->enableCache === false) {
            return false;
        }

        // \Yii::trace('Set to cache ' . $key . ' value ' . $value);

        $this->cache[$key] = $value;
    }

    private function hasCache($key)
    {
        if ($this->enableCache === false) {
            return false;
        }

        return key_exists($key, $this->cache);
    }

    private function getCache($key)
    {
        if ($this->enableCache === false) {
            return false;
        }

        // \Yii::trace('Get from cache ' . $key . ' value ' . $this->cache[$key]);

        return $this->cache[$key];
    }
}
