clementine-framework-module-db
==============================

Module pour faciliter l'abstraction de base de données.

Il fournit principalement des wrappers pour les fonctions mysqli_* ainsi que quelques fonctions utilitaires :
- foreign_keys
- distinct_values
- enum_values
- tag / untag

Configuration
-------------
```ini
[module_db]
log_queries=0 ; log les requetes avec la fonction error_log
use_apc=1     ; utiliser le cache APC
```
