<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProductsView->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProductsCreate->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsEdit->value);
    }

    public function manageVariants(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsManageVariants->value);
    }

    public function manageDocuments(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsManageDocuments->value);
    }

    public function downloadDocuments(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsDownloadDocuments->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsDelete->value);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsDelete->value);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsDelete->value);
    }
}
