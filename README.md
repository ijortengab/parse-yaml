Parse YAML
==================

ParseYAML adalah library PHP untuk mempersing string berformat YAML menjadi
variable. Library ini di-design untuk terintegrasi dengan Library
[Configuration Editor][1]. Untuk kebutuhan parse dan unparse sekaligus, maka
sebaiknya gunakan Library [Configuration Editor][1]. Namun jika hanya untuk
parsing, maka library ini sudah cukup memenuhi kebutuhan tersebut.

[1]: https://github.com/ijortengab/configuration-editor

## Requirements
 - PHP > 5.4
 - ijortengab/tools

## Comparison

Library PHP untuk parsing format YAML yang sudah exists adalah [syck], [spyc],
dan [symfony/yaml][symfony]. Tujuan utama dibuat library ini adalah untuk
mempertahankan *comment* yang terdapat pada informasi di format YAML agar tetap
exists saat dilakukan dump/unparse. Keunggulan ini-lah yang membedakan dengan
library parse YAML yang lain. Untuk mendapatkan fitur ini, gunakan library
[Configuration Editor][1].

[syck]: http://pecl.php.net/package/syck
[spyc]: https://github.com/mustangostang/spyc
[symfony]: http://symfony.com/doc/current/components/yaml/introduction.html

## Repository

Tambahkan code berikut pada composer.json jika project anda membutuhkan library
ini. Perhatikan _trailing comma_ agar format json anda tidak rusak.

```json
{
    "require": {
        "ijortengab/parse-yaml": "master"
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ijortengab/parse-yaml"
        }
    ]
}
```

## Usage
Disarankan untuk menghandle RuntimeException saat menjalankan method ::parse()
apabila format diragukan kevalidasiannya.
```php
use IjorTengab\ParseYAML\ParseYAML;
use IjorTengab\ParseYAML\RuntimeException;
require 'vendor/autoload.php'; // Sesuaikan dgn path anda.
$yml = file_get_contents('file.yml');
$obj = new ParseYAML($yml);
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

Berikut ini ada perbedaan hasil antara IjorTengab/ParseYAML dengan Symfony/Yaml

symfony/yaml berhasil memparsing string yaml sbb:
```
###
###
##
"aa: bb"
```
tapi Symfony throw error pada string yaml sbb:
```
###
###
##
"aa: bb"[space][space][space]
```
Sementara, untuk ParseYAML kedua format diatas resolve.
