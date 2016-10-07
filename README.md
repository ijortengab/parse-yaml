Parse YML
==================

ParseYML adalah library PHP untuk mempersing [YAML File Format][1]. File dot YML
adalah [salah satu file][2] yang digunakan untuk menyimpan configuration.
Library ini di-design untuk terintegrasi dengan Class [Configuration Editor][3].
Untuk kebutuhan unparse/dump array kedalam format dot yml, Anda dapat
menggunakan Class Configuration Editor.

[1]: https://en.wikipedia.org/wiki/YAML
[2]: https://en.wikipedia.org/wiki/Configuration_file
[3]: https://github.com/ijortengab/configuration-editor

## Requirements

 - PHP > 5.4
 - ```composer require psr/log```

## Comparison

Library PHP untuk parsing format YAML yang sudah exists adalah [syck], [spyc],
dan [symfony/yaml][4]. Perbedaan secara konsep dengan library lainnya ialah
perlakuan terhadap *comments* yang terdapat pada file YML. ParseYML jika digunakan bersama dengan
[ConfigurationEditor][3] akan menjaga *comments* tetap exists.

[syck]: http://pecl.php.net/package/syck
[spyc]: https://github.com/mustangostang/spyc
[4]: http://symfony.com/doc/current/components/yaml/introduction.html

## Repository

Tambahkan code berikut pada composer.json jika project anda membutuhkan library
ini. Perhatikan _trailing comma_ agar format json anda tidak rusak.

```json
{
    "require": {
        "ijortengab/parse-yml": "master"
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ijortengab/parse-yml"
        }
    ]
}
```

## Usage
Disarankan untuk menghandle RuntimeException saat menjalankan method ::parse()
apabila format diragukan kevalidasiannya.
```php
use IjorTengab\ParseYML\ParseYML;
use IjorTengab\ParseYML\RuntimeException;
require 'vendor/autoload.php'; // Sesuaikan dgn path anda.
$yml = file_get_contents('file.yml');
$obj = new ParseYML($yml);
try {
    $obj->parse();
}
catch(RuntimeException $e) {
    var_dump($e);
}
$result = $obj->getResult();
var_dump($result);
```


## Perbedaan hasil dengan Symfony/Yaml

Berikut ini ada perbedaan hasil antara IjorTengab/ParseYML dengan Symfony/Yaml

symfony/yaml berhasil memparsing string yml sbb:
```
###
###
##
"asldk: jfas"
```
tapi Symfony throw error pada string yml sbb:
```
###
###
##
"asldk: jfas"[space][space][space]
```
Sementara, untuk ParseYML kedua format diatas resolve.
