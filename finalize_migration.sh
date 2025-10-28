#!/bin/bash

# Script de finalisation de la migration du package filament-permission-manager
# Exécuter depuis la racine du projet x_filbreeze

echo "🎯 Finalisation de la migration vers filament-permission-manager"
echo "==============================================================="

# 1. Backup du composer.json actuel
echo "💾 Sauvegarde du composer.json..."
cp composer.json composer.json.backup

# 2. Ajouter le repository et la dépendance
echo "📦 Mise à jour du composer.json..."

# Utiliser jq si disponible, sinon modification manuelle nécessaire
if command -v jq &> /dev/null; then
    # Ajouter le repository
    jq '.repositories += [{"type": "path", "url": "./packages/filament-permission-manager"}]' composer.json > composer.tmp
    mv composer.tmp composer.json
    
    # Ajouter la dépendance
    jq '.require += {"charlesstolive/filament-permission-manager": "*"}' composer.json > composer.tmp
    mv composer.tmp composer.json
    
    echo "   ✅ composer.json mis à jour automatiquement"
else
    echo "   ⚠️  jq non disponible - modification manuelle nécessaire"
    echo "   📝 Ajouter manuellement dans composer.json :"
    echo '       "repositories": [{"type": "path", "url": "./packages/filament-permission-manager"}]'
    echo '       "require": {"charlesstolive/filament-permission-manager": "*"}'
fi

# 3. Installer le package
echo "🔧 Installation du package..."
composer require charlesstolive/filament-permission-manager

# 4. Mettre à jour UserResource
echo "👤 Mise à jour de UserResource..."
if [ -f "app/Filament/Resources/UserResource.php" ]; then
    sed -i 's/use App\\Services\\PermissionService;/use CharlesStOlive\\FilamentPermissionManager\\Services\\PermissionService;/g' "app/Filament/Resources/UserResource.php"
    echo "   ✅ Import mis à jour dans UserResource"
else
    echo "   ⚠️  UserResource non trouvé"
fi

# 5. Instructions pour AdminPanelProvider
echo ""
echo "🏛️  ÉTAPE MANUELLE REQUISE - AdminPanelProvider :"
echo "================================================"
echo "Dans app/Providers/Filament/AdminPanelProvider.php :"
echo ""
echo "1. Ajouter l'import :"
echo "   use CharlesStOlive\\FilamentPermissionManager\\FilamentPermissionManagerPlugin;"
echo ""
echo "2. Dans la méthode panel(), ajouter :"
echo "   ->plugins(["
echo "       FilamentPermissionManagerPlugin::make()"
echo "           ->navigationGroup('Administration')"
echo "           ->permissionResource()"
echo "           ->roleResource(),"
echo "   ])"

# 6. Test des commandes
echo ""
echo "🧪 Test des commandes..."
echo "========================"
php artisan list permissions

# 7. Instructions de nettoyage
echo ""
echo "🧹 NETTOYAGE FINAL (après tests) :"
echo "=================================="
echo "Supprimer ces fichiers une fois que tout fonctionne :"
echo "   - app/Console/Commands/SyncPermissions.php"
echo "   - app/Console/Commands/ResetPermissions.php"
echo "   - app/Console/Commands/GeneratePermissionSeeder.php"
echo "   - app/Console/Commands/AddResourcePermissions.php"
echo "   - app/Services/PermissionService.php"
echo "   - app/Filament/Resources/PermissionResource.php"
echo "   - app/Filament/Resources/RoleResource.php"
echo "   - app/Filament/Resources/PermissionResource/ (dossier)"
echo "   - app/Filament/Resources/RoleResource/ (dossier)"

echo ""
echo "🎉 Migration presque terminée !"
echo "Effectuer l'étape manuelle pour AdminPanelProvider, puis tester."