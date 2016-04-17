<?php

namespace IjorTengab;

use RuntimeException;

/**
 * Parser for YML file format.
 */
class ParseYML
{

    protected $is_scalar;

    protected $is_new_line = true;

    /**
     */
    protected $possibility;

    /**
     *
     */
    protected $possibility_next_step;


    /**
     *
     */
    public function analyzeCurrentStep($pch, $ch, $nch)
    {
        $current_step = $this->analyze_current_step;
        if (is_string($current_step)) {
            $_method = 'analyze_step_' . $this->analyze_current_step;
            $method = $this->camelCaseConvertFromUnderScore($_method);
            $this->{$method}($pch, $ch, $nch);
        }
        elseif (is_array($current_step)) {
            $found = false;
            foreach ($current_step as $_method => $action) {
                $method = $this->camelCaseConvertFromUnderScore($_method);
                if ($this->{$method}($ch)) {
                    $found = $_method;
                    break;
                }
            }
            if ($found === false) {
                $line_segmen = $this->line_segmen;
                $debugname = 'line_segmen'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                $lines = $this->lines;
                $debugname = 'lines'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                $data = $this->data;
                $debugname = 'data'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

                throw new RuntimeException;
            }
            if (array_key_exists('line_segmen', $current_step[$found])) {
                $segmen = $current_step[$found]['line_segmen'];
                $this->line_segmen[$segmen] .= $ch;
            }
            if (array_key_exists('analyze_next_step', $current_step[$found])) {
                $this->analyze_next_step = $current_step[$found]['analyze_next_step'];
            }
            if (array_key_exists('move_line_segmen', $current_step[$found])) {
                list($from, $to) = $current_step[$found]['move_line_segmen'];
                $this->line_segmen[$to] = $this->line_segmen[$from];
                $this->line_segmen[$from] = '';
            }
        }
    }






    /**
     *
     */
    public function checkIndent()
    {

        $checkIndent = true;
        $debugname = 'checkIndent'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";


        $this->is_new_line = false;
    }


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
    protected $analyze_current_step = 'init';

    /**
     * Tahapan membangun parsing berikutnya.
     * Property ini diubah oleh semua method analyze.
     */
    protected $analyze_next_step;

    /**
     * Karakter yang dianalisis dari keseluruhan karaakter.
     * Karakter pertama diawali dari angka 0.
     */
    protected $analyze_current_char = 0;

    /**
     * Baris saat ini yang sedang dianalisis.
     * Baris pertama diawali dari angka 1.
     */
    protected $analyze_current_line = 1;

    /**
     * Kolom dari baris saat ini yang sedang dianalisis.
     * Kolom pertama diawali dari angka 1.
     */
    protected $analyze_current_column = 1;

    protected $analyze_current_indent;

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
            'key_prepend' => '',
            'key' => '',
            'key_append' => '',
            'separator' => '',
            'quote' => '',
            'value_prepend' => '',
            'value' => '',
            'value_append' => '',
            'comment' => '',
            'eol' => '',
        ];
    }

    /**
     *
     */
    public function isAlphaNumeric($ch)
    {
        return ctype_alnum($ch);
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
        return ($ch === ':');
    }

    /**
     *
     */
    public function isCommentSign($ch)
    {
        return ($ch === '#');
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
            $x = $this->analyze_current_char;
            $ch = isset($string[$x]) ? $string[$x] : false;
            $nch = isset($string[$x+1]) ? $string[$x+1] : false;
            $pch = isset($string[$x-1]) ? $string[$x-1] : false;
            if ($ch == "\r" && $nch == "\n") {
                $ch = "\r\n";
                $nch = isset($string[$x+2]) ? $string[$x+2] : false;
                $this->analyze_current_char++;
            }
            // if ($this->is_new_line && $this->analyze_current_step != 'build_value') {
                // $this->checkIndent();
            // }

            $analyze_current_step = $this->analyze_current_step;
            $debugname = 'analyze_current_step'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
            $debugname = 'ch'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

            // Let's Analyze.
            $this->analyzeCurrentStep($pch, $ch, $nch);

            // Finishing for the end of file
            // if file not ending with EOL.
            if ($this->isBreak($ch) === false &&  $nch === false) {
                $analyze_current_step = $this->analyze_current_step;
                if ($this->analyze_current_step === 'build_value') {
                    $segmen = $this->line_segmen;
                    $debugname = 'segmen'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                    $this->analyzeFinish();
                }
                // $debugname = 'analyze_current_step'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                $this->lines[$this->analyze_current_line] = $this->line_segmen;
                $this->line_segmen = null;
            }
            else {
                // Prepare for the next character analyze.
                $this->analyze_current_char++;
                $this->analyze_current_column++;
                if (null !== $this->analyze_next_step) {
                    $this->analyze_current_step = $this->analyze_next_step;
                    $this->analyze_next_step = null;
                }
                if (null !== $this->possibility_next_step) {
                    $this->possibility = $this->possibility_next_step;
                    $this->possibility_next_step = null;
                }
                if ($this->isBreak($ch)) {
                    if ($this->analyze_current_step != 'build_value') {
                        $this->lines[$this->analyze_current_line] = $this->line_segmen;
                        $this->line_segmen = $this->lineSegmenReference();
                        $this->is_new_line = true;
                    }
                    $this->analyze_current_line++;
                    $this->analyze_current_column = 1;
                }
            }

            // if ($x == 21) {
                // break;
            // }
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
        if ($this->isAlphaNumeric($ch)) {
            // Jika karakter sebelumnya adalah whitespace, maka error
            if ($this->isWhiteSpace($pch)) {
                throw new RuntimeException;
            }
            $this->line_segmen['key'] .= $ch;
            $this->analyze_next_step = 'build_key';
        }
        elseif ($this->isBreak($ch)) {
            $this->line_segmen['eol'] .= $ch;
        }
        elseif ($this->isWhiteSpace($ch)) {
            // throw new RuntimeException;
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
        }
        elseif ($this->isCommentSign($ch)) {
        }
        elseif ($ch == '-' && $this->isWhiteSpace($nch)) {
            $this->is_scalar = true;
            $this->line_segmen['value_prepend'] .= $ch;
            $this->analyze_next_step = [
                'is_quote' => [
                    'line_segmen' => 'quote',
                    'analyze_next_step' => 'build_value',
                ],
                'is_alpha_numeric' => [
                    'line_segmen' => 'key',
                    'analyze_next_step' => 'build_key',
                    'move_line_segmen' => ['value_prepend', 'key_prepend'],
                ],
                'is_white_space' => [
                    'line_segmen' => 'value_prepend',
                    // 'analyze_next_step' => 'build_value_prepend',
                ],
            ];
        }
        else {
            $this->line_segmen['key'] .= $ch;
            $this->analyze_next_step = 'build_key';
        }
    }

    /**
     *
     */
    public function analyzeStepBuildKey($pch, $ch, $nch)
    {
        if ($this->isAlphaNumeric($ch)) {
            $this->line_segmen['key'] .= $ch;
            $this->analyze_next_step = 'build_key';
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['key_append'] .= $ch;
            $this->analyze_next_step = 'build_key_append';
        }
        elseif ($this->isQuote($ch)) {
            // $this->line_segmen['quote'] .= $ch;
            // $this->analyze_next_step = 'build_value_prepend';
        }
        elseif ($this->isSeparator($ch)) {
            $this->line_segmen['separator'] .= $ch;
            // Karakter sesudah separator harus whitespace.
            if (!ctype_space($nch)) {
                throw new RuntimeException;
            }
            $this->analyze_next_step = 'build_value_prepend';
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
        if ($this->isAlphaNumeric($ch)) {
            $this->line_segmen['value'] .= $ch;
            $this->analyze_next_step = 'build_value';
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['key_append'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
        }
        elseif ($this->isSeparator($ch)) {
            $this->line_segmen['separator'] .= $ch;
            // Karakter sesudah separator harus whitespace.
            if (!ctype_space($nch)) {
                throw new RuntimeException;
            }
            $this->analyze_next_step = 'build_value_prepend';
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
        if ($this->isAlphaNumeric($ch)) {
            $this->line_segmen['value'] .= $ch;
            $this->analyze_next_step = 'build_value';
        }
        elseif ($this->isBreak($ch)) {
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['value_prepend'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
            $this->line_segmen['quote'] .= $ch;
            $this->analyze_next_step = 'build_value';
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
        if ($this->isAlphaNumeric($ch)) {
            $this->line_segmen['value'] .= $ch;
        }
        elseif ($this->isBreak($ch)) {
            if (!empty($this->line_segmen['quote'])) {
                $this->line_segmen['value'] .= $ch;
            }
            else {
                $this->line_segmen['eol'] .= $ch;
                $this->analyzeFinish();
                $this->analyze_next_step = 'init';
            }

            // $a = $this->line_segmen;
            // $debugname = 'a'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
            // die;
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['value'] .= $ch;
        }
        elseif ($this->isQuote($ch)) {
            if ($ch == '"' && $this->line_segmen['quote'] == '"') {
                $this->analyze_next_step = 'build_value_append';
            }
            elseif ($ch == "'" && $this->line_segmen['quote'] == "'") {
                if ($nch == "'") {
                    // Kasus:
                    // key: 'value''value'
                    $this->line_segmen['value'] .= $ch;
                    $this->analyze_current_char++;
                }
                else {
                    $this->analyze_next_step = 'build_value_append';
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
                $this->analyze_current_char++;
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
        if ($this->isAlphaNumeric($ch)) {
        }
        elseif ($this->isBreak($ch)) {
            $this->line_segmen['eol'] .= $ch;
            $this->analyzeFinish();
            $this->analyze_next_step = 'init';
        }
        elseif ($this->isWhiteSpace($ch)) {
            $this->line_segmen['value_append'] .= $ch;
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
        if ($this->isAlphaNumeric($ch)) {
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
        // Perbaiki value.
        $value = $this->line_segmen['value'];
        // Jika tidak ada quote, maka
        if (empty($this->line_segmen['quote'])) {
            if (is_numeric($value)) {
                $value = (int) $value;
            }
        }
        elseif ($this->line_segmen['quote'] == '"') {
            // Todo. Jika unparse, maka kembalikan ke /n
            $from[] = '\r'; $to[] = "\r";
            $from[] = '\n'; $to[] = "\n";
            $value = str_replace($from, $to, $value);
        }

        // Perbaiki key.
        $key = $this->line_segmen['key'];
        // $debugname = 'key'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        if (empty($key)) {
            $this->data[] = $value;
        }
        else {
            $this->data[$key] = $value;
        }
        // $data = $this->data;
        // $db = debug_backtrace();
        // $debugname = 'db'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

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
