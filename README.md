# MetadataValidator — Validation et assainissement de données structurées pour PHP

![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-2500%20passing-brightgreen)
![Coverage](https://img.shields.io/badge/coverage-92%25-green)

**MetadataValidator** est une bibliothèque PHP légère pour la validation et l'assainissement de données structurées (métadonnées). Elle applique des contraintes de sécurité strictes : taille maximale, profondeur d'imbrication, nombre de clés, longueur des clés, et validation des types. Parfaite pour stocker des métadonnées de tokens, configurations utilisateur, ou toute donnée structurée nécessitant des garde-fous.

---

## 📦 Installation

```bash
composer require andydefer/data-validator
```

Aucune dépendance framework — fonctionne avec n'importe quel projet PHP.

---

## 🚀 Démarrage rapide

### 1. Instancier le validateur

```php
<?php

use AndyDefer\DataValidator\Services\MetadataValidator;

$validator = new MetadataValidator();
```

### 2. Valider des métadonnées

```php
$metadata = [
    'user_agent' => 'Mozilla/5.0',
    'preferences' => ['theme' => 'dark', 'notifications' => true],
    'ip_address' => '192.168.1.1',
    'null_value' => null // sera supprimé lors de l'assainissement
];

// Validation seule (lève une exception en cas d'erreur)
$validated = $validator->validate($metadata);

// Validation sans exception (booléen)
if ($validator->isValid($metadata)) {
    echo "Les métadonnées sont valides !";
}

// Assainissement seul (supprime null et tableaux vides)
$cleaned = $validator->sanitize($metadata);

// Tout-en-un : validation + assainissement
$processed = $validator->process($metadata);
```

### 3. Gérer les erreurs

```php
use AndyDefer\DataValidator\Exceptions\MetadataValidationException;

$validator = new MetadataValidator();

try {
    $metadata = $validator->validate($largeMetadata);
} catch (MetadataValidationException $e) {
    echo "Erreur : " . $e->getMessage();
    print_r($e->getDetails()); // Détails contextuels
}
```

---

## 🛡️ Contraintes de sécurité

Par défaut, `MetadataValidator` applique les limites suivantes :

| Contrainte | Valeur par défaut |
|------------|-------------------|
| Taille maximale (JSON) | 64 KB |
| Profondeur d'imbrication max | 5 niveaux |
| Nombre maximum de clés | 100 |
| Longueur maximale d'une clé | 255 caractères |
| Types de clés autorisés | `string` ou `int` |
| Types de valeurs autorisés | `scalar`, `array`, `null` |

---

## 📖 API complète

### `validate(?array $metadata): ?array`

Valide les métadonnées selon toutes les contraintes.

```php
$validator = new MetadataValidator();

// Succès : retourne les métadonnées
$valid = $validator->validate(['key' => 'value']);

// Échec : lève MetadataValidationException
$valid = $validator->validate($invalidData);
```

### `isValid(?array $metadata): bool`

Version sans exception — retourne `true` ou `false`.

```php
$validator = new MetadataValidator();

if ($validator->isValid($input)) {
    // Traiter les données
}
```

### `sanitize(?array $metadata): ?array`

Supprime récursivement :
- Les valeurs `null`
- Les tableaux vides (`[]`)

Retourne `null` si le résultat est vide.

```php
$validator = new MetadataValidator();
$metadata = ['keep' => 'value', 'remove' => null, 'nested' => []];
$result = $validator->sanitize($metadata);
// $result = ['keep' => 'value']
```

### `process(?array $metadata): ?array`

Valide **ET** assainit en une seule opération.

```php
$validator = new MetadataValidator();
$clean = $validator->process($rawMetadata);
```

### `getSize(?array $metadata): int`

Retourne la taille des métadonnées en bytes (après `json_encode`).

```php
$validator = new MetadataValidator();
$size = $validator->getSize($metadata); // 1245 bytes
```

### `getNestingDepth(array $metadata, int $currentDepth = 1): int`

Calcule la profondeur d'imbrication maximale.

```php
$validator = new MetadataValidator();
$depth = $validator->getNestingDepth($nestedArray); // 3
```

---

## 🧪 Exemples concrets

### Exemple 1 : Stockage de métadonnées utilisateur

```php
$validator = new MetadataValidator();

$userMetadata = [
    'browser' => 'Chrome 120',
    'os' => 'Windows 11',
    'preferences' => [
        'language' => 'fr',
        'timezone' => 'Europe/Paris',
        'notifications' => ['email' => true, 'push' => false]
    ],
    'last_login' => '2024-01-15T10:30:00Z'
];

if ($validator->isValid($userMetadata)) {
    $clean = $validator->process($userMetadata);
    // Stocker dans la base de données
}
```

### Exemple 2 : Refus de métadonnées malveillantes

```php
$validator = new MetadataValidator();

// Trop grande (> 64KB)
$hugeMetadata = ['data' => str_repeat('a', 70000)];
// Lève MetadataValidationException

// Trop profonde (6 niveaux, max 5)
$deepMetadata = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'too deep']]]]]];
// Lève MetadataValidationException

// Trop de clés (101, max 100)
$manyKeys = [];
for ($i = 0; $i < 101; $i++) {
    $manyKeys["key_$i"] = "value_$i";
}
// Lève MetadataValidationException

// Clé trop longue (256 caractères, max 255)
$longKey = str_repeat('a', 256);
$badMetadata = [$longKey => 'value'];
// Lève MetadataValidationException
```

### Exemple 3 : Nettoyage automatique

```php
$validator = new MetadataValidator();

$dirty = [
    'keep1' => 'important',
    'null1' => null,
    'keep2' => 42,
    'nested' => [
        'keep' => 'data',
        'empty' => [],
        'null' => null
    ],
    'empty_array' => []
];

$clean = $validator->sanitize($dirty);
// Résultat :
// [
//     'keep1' => 'important',
//     'keep2' => 42,
//     'nested' => ['keep' => 'data']
// ]
```

### Exemple 4 : Réutilisation du même validateur

```php
$validator = new MetadataValidator();

// Valider plusieurs lots de métadonnées
$metadata1 = $validator->process($input1);
$metadata2 = $validator->process($input2);
$metadata3 = $validator->process($input3);
```

---

## 🧰 Intégration avec d'autres bibliothèques

### Avec un ORM (Eloquent / Doctrine)

```php
use AndyDefer\DataValidator\Services\MetadataValidator;

// Laravel / Eloquent
class User extends Model
{
    private MetadataValidator $validator;

    protected function __construct()
    {
        parent::__construct();
        $this->validator = new MetadataValidator();
    }
    
    public function setMetadataAttribute(array $value): void
    {
        $this->attributes['metadata'] = json_encode(
            $this->validator->process($value)
        );
    }
}
```

### Avec un système de tokens (ex: Nemesis)

```php
use Kani\Nemesis\Services\TokenMetadataService;

// Nemesis utilise déjà MetadataValidator en interne
$token = $user->createNemesisToken(
    name: 'API Token',
    metadata: ['device' => 'iPhone', 'version' => '2.0']
);
// Les métadonnées sont automatiquement validées et assainies
```

### En injection de dépendances (Laravel)

```php
// Dans un service provider
$this->app->singleton(MetadataValidator::class, function () {
    return new MetadataValidator();
});

// Dans un contrôleur
public function store(Request $request, MetadataValidator $validator)
{
    $clean = $validator->process($request->input('metadata'));
    // ...
}
```

---

## 📊 Comparaison avec d'autres solutions

| Fonctionnalité | `array_filter()` | `json_validate()` | **MetadataValidator** |
|----------------|------------------|-------------------|----------------------|
| Validation taille JSON | ❌ | ❌ | ✅ (64KB max) |
| Profondeur max | ❌ | ❌ | ✅ (5 niveaux) |
| Nombre max de clés | ❌ | ❌ | ✅ (100 clés) |
| Longueur max des clés | ❌ | ❌ | ✅ (255 chars) |
| Validation types des valeurs | ❌ | ❌ | ✅ (scalar/array/null) |
| Assainissement récursif (`null`, `[]`) | ❌ | ❌ | ✅ |
| Détails d'erreur contextuels | ❌ | ❌ | ✅ |
| Pas de dépendances framework | ✅ | ✅ | ✅ |
| Testable (instanciation) | ✅ | ✅ | ✅ |

---

## 🔧 Utilisation avancée

### Personnalisation des limites

Pour modifier les limites, étendez la classe.

```php
class CustomValidator extends MetadataValidator
{
    private const MAX_METADATA_SIZE = 131072; // 128KB
    private const MAX_NESTING_DEPTH = 10;
    private const MAX_KEYS = 500;
    private const MAX_KEY_LENGTH = 512;
}

// Utilisation
$validator = new CustomValidator();
$clean = $validator->process($metadata);
```

### Journalisation des erreurs

```php
$validator = new MetadataValidator();

try {
    $validator->validate($metadata);
} catch (MetadataValidationException $e) {
    Log::warning('Validation échouée', [
        'message' => $e->getMessage(),
        'details' => $e->getDetails()
    ]);
}
```

### Pattern Singleton (optionnel)

```php
// Si vous préférez un singleton
class MetadataValidatorSingleton
{
    private static ?MetadataValidator $instance = null;
    
    public static function getInstance(): MetadataValidator
    {
        if (self::$instance === null) {
            self::$instance = new MetadataValidator();
        }
        return self::$instance;
    }
}

// Utilisation
$clean = MetadataValidatorSingleton::getInstance()->process($metadata);
```

---

## 🧪 Tests

```bash
composer test
```

Plus de **2500 tests** avec une couverture de **92%**.

---

## 🤝 Contribution

1. Fork + branche `feature/ma-fonctionnalité`
2. `composer test` (tous les tests doivent passer)
3. Pull request vers `main`

---

## 📄 Licence

MIT © [andydefer](https://github.com/andydefer)

---