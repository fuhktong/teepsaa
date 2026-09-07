<?php

// The category tree, and the addresses its branches answer on.
//
// Categories have no public_id — they aren't secret and there is nothing to
// enumerate — so a category address is just its words: /category/dresses/.
//
// The whole table is 49 rows, so every function here works from one query
// held in a static for the rest of the request. Building the slugs in PHP
// rather than storing a column keeps this a code change with no migration to
// hand-apply on the server.

require_once __DIR__ . '/slug.php';

// id => row, in id order. One query per request.
if (!function_exists('category_all')) {
    function category_all(PDO $pdo): array {
        static $rows = null;
        if ($rows === null) {
            $rows = [];
            foreach ($pdo->query('SELECT id, parent_id, name, name_km FROM categories ORDER BY id') as $r) {
                $rows[(int)$r['id']] = $r;
            }
        }
        return $rows;
    }
}

// id => slug.
//
// Seven names appear twice in the tree — Jeans, Shorts, Activewear and the
// rest sit under both Men's and Women's — so a bare name is not an address.
// When a name is shared, *every* holder of it takes the parent's name as a
// prefix (mens-jeans, womens-jeans) rather than the first one keeping the
// bare slug: symmetric, and it means adding "Kids' Jeans" later doesn't
// silently move an address that is already in Google's index.
if (!function_exists('category_slugs')) {
    function category_slugs(PDO $pdo): array {
        static $slugs = null;
        if ($slugs !== null) return $slugs;

        $rows = category_all($pdo);

        $base = [];
        foreach ($rows as $id => $r) $base[$id] = slugify((string)$r['name']) ?: ('c' . $id);

        $shared = [];
        foreach ($base as $s) $shared[$s] = ($shared[$s] ?? 0) + 1;

        $slugs = [];
        $taken = [];
        foreach ($rows as $id => $r) {
            $s = $base[$id];
            if (($shared[$s] ?? 0) > 1) {
                $parentId = (int)($r['parent_id'] ?? 0);
                $prefix   = $parentId && isset($rows[$parentId]) ? slugify((string)$rows[$parentId]['name']) : '';
                if ($prefix !== '') $s = $prefix . '-' . $s;
            }
            // Belt and braces: two different branches could still land on the
            // same string. The id is ugly but it is never wrong.
            if (isset($taken[$s])) $s .= '-' . $id;
            $taken[$s] = true;
            $slugs[$id] = $s;
        }
        return $slugs;
    }
}

if (!function_exists('category_path')) {
    function category_path(PDO $pdo, $cat): string {
        $id = is_array($cat) ? (int)$cat['id'] : (int)$cat;
        $slugs = category_slugs($pdo);
        return isset($slugs[$id]) ? '/category/' . rawurlencode($slugs[$id]) . '/' : '/search/?category=' . $id;
    }
}

if (!function_exists('category_by_slug')) {
    function category_by_slug(PDO $pdo, string $slug): ?array {
        $slug = rawurldecode($slug);
        $id   = array_search($slug, category_slugs($pdo), true);
        if ($id === false) return null;
        return category_all($pdo)[$id] ?? null;
    }
}

// This category and everything under it.
//
// Vendors file products against leaves ("Dresses"), never against a parent,
// so /category/womens/ has to ask for the whole branch or it shows an empty
// grid on a category that plainly has products in it.
if (!function_exists('category_branch_ids')) {
    function category_branch_ids(PDO $pdo, int $id): array {
        $children = [];
        foreach (category_all($pdo) as $cid => $r) {
            $children[(int)($r['parent_id'] ?? 0)][] = $cid;
        }
        $ids   = [];
        $queue = [$id];
        while ($queue) {
            $cur = array_pop($queue);
            if (isset($ids[$cur])) continue;   // a bad parent_id loop can't hang the page
            $ids[$cur] = true;
            foreach ($children[$cur] ?? [] as $kid) $queue[] = $kid;
        }
        return array_keys($ids);
    }
}

// Home › Clothing › Women's › Dresses — the ancestors of a category, root
// first, itself last.
if (!function_exists('category_ancestors')) {
    function category_ancestors(PDO $pdo, int $id): array {
        $rows  = category_all($pdo);
        $trail = [];
        $seen  = [];
        while ($id && isset($rows[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            array_unshift($trail, $rows[$id]);
            $id = (int)($rows[$id]['parent_id'] ?? 0);
        }
        return $trail;
    }
}
