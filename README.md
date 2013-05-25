Module DB
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
