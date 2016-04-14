<?php

namespace IjorTengab;

use RuntimeException;

/**
 * Parser for YML file format.
 */
class ParseYML
{
    /**
     * Isi content yang akan diparsing.
     */
    public $raw;

    /**
     * Penanda apakah telah dilakukan parsing untuk mencegah double parsing.
     * Saat instance baru dibuat, maka parsing belum dilakukan. Nilai otomatis
     * menjadi true jika method ::parse() dijalankan.
     */
    protected $has_parsing = false;

    /**
     * Hasil parsing.
     */
    public $data = [];

    /**
     * Penampungan sementara key yang bersifat scalar, untuk nantinya dihitung
     * index autoincrement saat dilakukan parsing.
     * Contoh:
     * [
     *   "key[child][]",
     *   "key[child][]",
     *   "key[child][]",
     * ]
     * Pada array diatas, maka key[child][2] adalah index tertinggi.
     */
    protected $key_scalar = [];

    /**
     * Mapping antara key data dengan line pada file.
     * Array sederhana satu dimensi, dimana key merupakan array simplify
     * dan value merupakan baris pada file.
     * Contoh:
     * [
     *   "key[child][0]" => 1,
     *   "key[child][1]" => 2,
     *   "key[child][2]" => 3,
     *   "other-key" => 4,
     * ]
     */
    public $data_map = [];

    /**
     * Referensi untuk EOL yang mayoritas pada file. Value pada property ini
     * akan berubah setelah dilakukan parsing.
     */
    protected $most_eol = "\n";

    /**
     * Index array tempat menampung informasi per line. Tiap value, merupakan
     * array yang berisi segmen dari line tersebut.
     *
     * @see: ::lineSegmenReference().
     */
    protected $lines = [];

    /**
     * Penampungan sementara sebelum digabung kedalam property $lines.
     */
    protected $line_segmen;

    /**
     * Tahapan membangun parsing saat ini.
     * Diawali dengan step 'init'.
     * @see ::parseString().
     */
    protected $current_analyze_step = 'init';

    /**
     * Tahapan membangun parsing berikutnya.
     * Property ini diubah oleh semua method analyze.
     */
    protected $next_analyze_step;

    /**
     * Karakter yang dianalisis dari keseluruhan karaakter.
     * Karakter pertama diawali dari angka 0.
     */
    protected $current_analyze_char = 0;

    /**
     * Baris saat ini yang sedang dianalisis.
     * Baris pertama diawali dari angka 1.
     */
    protected $current_analyze_line = 1;

    /**
     * Kolom dari baris saat ini yang sedang dianalisis.
     * Kolom pertama diawali dari angka 1.
     */
    protected $current_analyze_column = 1;

    /**
     * Construct.
     */
    function __construct($raw = null)
    {
        $this->raw = $raw;
    }

    /**
     * Referensi yang akan digunakan saat parsing, berisi informasi default
     * dari property $line_segmen.
     */
    public function lineSegmenReference()
    {
        return [
            'key prepend' => '',
            'key' => '',
            'key append' => '',
            'separator' => '',
            'quote' => '',
            'value prepend' => '',
            'value' => '',
            'value append' => '',
            'comment' => '',
            'eol' => '',
        ];
    }

    /**
     *
     */
    public function isBreak($ch)
    {
        return in_array($ch, ["\r", "\n", "\r\n"]);
    }

    /**
     * Mengecek karakter WhiteSpace (selain Break).
     */
    public function isWhiteSpace($ch)
    {
        return (ctype_space($ch) && !in_array($ch, ["\r", "\n", "\r\n"]));
    }

    /**
     *
     */
    public function isQuote($ch)
    {
        return in_array($ch, ["'", '"']);
    }

    /**
     *
     */
    public function isSeparator($ch)
    {
        return in_array($ch, [':']);
    }

    /**
     *
     */
    public function isCommentSign($ch)
    {
        return in_array($ch, ['#']);
    }

    /**
     * Melakukan parsing.
     */
    public function parse()
    {
        if (false === $this->has_parsing) {
            $this->has_parsing = true;
            if (is_string($this->raw)) {
                $this->parseString($this->raw);
            }
        }
    }

    /**
     * Melakukan parsing pada string berformat INI.
     */
    protected function parseString($string)
    {
        // Prepare.
        $this->line_segmen = $this->lineSegmenReference();
        do {
            $x = $this->current_analyze_char;
            $ch = isset($string[$x]) ? $string[$x] : false;
            $nch = isset($string[$x+1]) ? $string[$x+1] : false;
            $pch = isset($string[$x-1]) ? $string[$x-1] : false;
            if ($ch == "\r" && $nch == "\n") {
                $ch = "\r\n";
                $nch = isset($string[$x+2]) ? $string[$x+2] : false;
                $this->current_analyze_char++;
            }

            $debugname = 'ch'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

            // Let's Analyze.
            $_method = 'analyze_step_' . $this->current_analyze_step;
            $method = $this->camelCaseConvertFromUnderScore($_method);
            $this->{$method}($pch, $ch, $nch);

            // Finishing for the end of file.
            if ($nch === false) {
                $current_analyze_step = $this->current_analyze_step;
                if ($this->current_analyze_step === 'build_value') {
                    $this->analyzeFinish();
                }
                $debugname = 'current_analyze_step'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

                $this->lines[$this->current_analyze_line] = $this->line_segmen;
                $this->line_segmen = null;
            }
            else {
                // Prepare for the next character analyze.
                $this->current_analyze_char++;
                $this->current_analyze_column++;
                if (null !== $this->next_analyze_step) {
                    $this->current_analyze_step = $this->next_analyze_step;
                    $this->next_analyze_step = null;
                }
                if ($this->isBreak($ch)) {
                    $this->lines[$this->current_analyze_line] = $this->line_segmen;
                    $this->line_segmen = $this->lineSegmenReference();
                    $this->current_analyze_line++;
                    $this->current_analyze_column = 1;
                }
            }
        } while($nch !== false);

        $line_segmen = $this->line_segmen;
        $debugname = 'line_segmen'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        $lines = $this->lines;
        $debugname = 'lines'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        $data = $this->data;
        $debugname = 'data'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";




    }


    /**
     *
     */
    public function analyzeStepInit($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
            $this->line_segmen['key'] .= $ch;
            $this->next_analyze_step = 'build_key';
        }
        elseif ($this->isBreak($ch)) {
            $this->line_segmen['eol'] .= $ch;
        }
        elseif ($this->isWhiteSpace($ch)) {
            throw new RuntimeException;
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
        }
        elseif ($this->isCommentSign($ch)) {
        }
        else {
            $this->line_segmen['key'] .= $ch;
            $this->next_analyze_step = 'build_key';
        }
    }


    /**
     *
     */
    public function analyzeStepBuildKey($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
            $this->line_segmen['key'] .= $ch;
            $this->next_analyze_step = 'build_key';
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['key append'] .= $ch;
            $this->next_analyze_step = 'build_key_append';
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
            $this->line_segmen['separator'] .= $ch;
            // Karakter sesudah separator harus whitespace.
            if (!ctype_space($nch)) {
                throw new RuntimeException;
            }
            $this->next_analyze_step = 'build_value_prepend';
        }
        elseif ($this->isCommentSign($ch)) {
        }
        else {
        }
    }

    /**
     *
     */
    public function analyzeStepBuildKeyAppend($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
            $this->line_segmen['value'] .= $ch;
            $this->next_analyze_step = 'build_value';
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['key append'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
            $this->line_segmen['separator'] .= $ch;
            // Karakter sesudah separator harus whitespace.
            if (!ctype_space($nch)) {
                throw new RuntimeException;
            }
            $this->next_analyze_step = 'build_value_prepend';
        }
        elseif ($this->isCommentSign($ch)) {
        }
        else {
        }
    }

    /**
     *
     */
    public function analyzeStepBuildValuePrepend($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
            $this->line_segmen['value'] .= $ch;
            $this->next_analyze_step = 'build_value';
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['value prepend'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
            $this->line_segmen['quote'] .= $ch;
            $this->next_analyze_step = 'build_value';
        }
        elseif ($this->isSeparator($ch)) {
        }
        elseif ($this->isCommentSign($ch)) {
        }
        else {
        }
    }

    /**
     *
     */
    public function analyzeStepBuildValue($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
            $this->line_segmen['value'] .= $ch;
        }
        elseif ($this->isBreak($ch)) {
            $this->line_segmen['eol'] .= $ch;
            $this->analyzeFinish();
            $this->next_analyze_step = 'init';
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['value'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
            if ($ch == '"' && $this->line_segmen['quote'] == '"') {
                $this->next_analyze_step = 'build_value_append';
            }
            elseif ($ch == "'" && $this->line_segmen['quote'] == "'") {
                if ($nch == "'") {
                    // Kasus:
                    // key: 'value''value'
                    $this->line_segmen['value'] .= $ch;
                    $this->current_analyze_char++;
                }
                else {
                    $this->next_analyze_step = 'build_value_append';
                }
                // Todo: saat unparse, maka jika quote menggunakan
                // singlequote, maka jika value terdapat singlequote
                // jadikan 2x singlequote.
            }
            elseif ($ch == '"' && $this->line_segmen['quote'] == "'") {
                $this->line_segmen['value'] .= $ch;
            }
            elseif ($ch == "'" && $this->line_segmen['quote'] == '"') {
                $this->line_segmen['value'] .= $ch;
            }
            // $klm = $this->line_segmen;
            // $debugname = 'klm'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        }
        elseif ($this->isSeparator($ch)) {
            $this->line_segmen['value'] .= $ch;
        }
        elseif ($this->isCommentSign($ch)) {
            $this->line_segmen['value'] .= $ch;
        }
        elseif ($ch == '\\') {
            if($nch == '"' && $this->line_segmen['quote'] == '"') {
                // Kasus:
                // key: "value\"value\"value"
                $this->line_segmen['value'] .= '"';
                $this->current_analyze_char++;
            }
            else {
                $this->line_segmen['value'] .= $ch;
            }
        }
        else {
            $this->line_segmen['value'] .= $ch;
        }
    }

    /**
     *
     */
    public function analyzeStepBuildValueAppend($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
        }
        elseif ($this->isBreak($ch)) {
            $this->line_segmen['eol'] .= $ch;
            $this->analyzeFinish();
            $this->next_analyze_step = 'init';
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['value append'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
        }
        elseif ($this->isCommentSign($ch)) {
        }
        else {
        }
    }

    /**
     *
     */
    public function analyzeStepTemplate($pch, $ch, $nch)
    {
        if (ctype_alnum($ch)) {
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
        }
        elseif ($this->isCommentSign($ch)) {
        }
        else {
        }
    }

    /**
     *
     */
    public function analyzeFinish()
    {
        $value = $this->line_segmen['value'];
        // Jika tidak ada quote, maka
        if (empty($this->line_segmen['quote'])) {
            if (is_numeric($value)) {
                $value = (int) $value;
            }
        }
        $this->data[$this->line_segmen['key']] = $value;

    }

    /**
     * Copy dari IjorTengab\Tools\Functions\ArrayDimensional::expand()
     * untuk menghindari require ijortengab/tools.
     */
    public static function arrayDimensionalExpand($array_simple)
    {
        $info = [];
        foreach ($array_simple as $key => $value) {
            $keys = preg_split('/\]?\[/', rtrim($key, ']'));
            $last = array_pop($keys);
            $parent = &$info;
            // Create nested arrays.
            foreach ($keys as $key) {
                if ($key == '') {
                    $key = count($parent);
                }
                if (!isset($parent[$key]) || !is_array($parent[$key])) {
                    $parent[$key] = array();
                }
                $parent = &$parent[$key];
            }
            // Insert actual value.
            if ($last == '') {
                $last = count($parent);
            }
            $parent[$last] = $value;
        }
        return $info;
    }

    /**
     * Copy dari IjorTengab\Tools\Functions\CamelCase::convertFromUnderScore()
     * untuk menghindari require ijortengab/tools.
     */
    public static function camelCaseConvertFromUnderScore($string)
    {
        // Prepare Cache.
        $string = trim($string);
        if ($string === '') {
            return;
        }
        static $cache = [];
        if (isset($cache[$string])) {
            return $cache[$string];
        }

        // Action.
        $result = $string;
        $result = strtolower($result);
        $explode = explode('_', $result);
        do {
            if (!isset($x)) {
                $x = 0;
                $method = $explode[$x];
            }
            else {
                $method .= ucfirst($explode[$x]);
            }
        } while(count($explode) > ++$x);
        $cache[$string] = $method;
        return $method;
    }

}
