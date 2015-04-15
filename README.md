Clementine Framework : module DB
=========

Module pour faciliter l'abstraction de base de données.

Il fournit principalement des wrappers :
- fonctions mysqli_* 

Il fournit aussi quelques fonctions utilitaires :
- foreign_keys : renvoie les clés étrangères d'une table
- distinct_values : renvoie toutes les valeurs distinctes d'un champ
- enum_values : renvoie les valeurs possibles d'un champ enum
- tag / untag : tag / untag toutes les prochaines requetes qui seront exécutées (pour faciliter le debug)

Configuration
-------------
```ini
[module_db]
log_queries=0 ; log les requêtes avec la fonction error_log
use_apc=1     ; utiliser le cache APC
```

Tags et debug
---
Avec ce module, on peut taguer toutes les requêtes exécutées entre 2 points du code :
```php
$this->getModel('db')->tag('red');
...
$this->getModel('db')->untag();
```

Cela affectera un tag aux requêtes qui sont logguées ou affichées dans le tableau de debug.

Un **tag** peut être une _valeur spéciale_ ou un _texte_ :
- les _valeurs spéciales_ seront traduites en couleurs dans le log d'erreurs. _Valeurs : `blue`, `err`, `error`, `fatal`, `green`, `info`, `orange`, `red`, `warn`, `warning`, `yellow`_
- un texte sera affiché tel quel. On peut s'en servir pour filtrer le log d'erreurs avec `grep`. On peut aussi utiliser les 2 paramètres `opening_tag` et `closing_tag` avec du code HTML pour mettre en évidence certaines requêtes dans le tableau d'erreurs.
