<?php

namespace App\Controllers;

use App\Core\BlogPosts;
use App\Core\Config;
use App\Core\Router;
use App\Core\View;

class BlogController
{
    public function index(): void
    {
        View::render('site/blog/index', [
            'posts' => BlogPosts::POSTS,
            'seoTitle' => 'Blog — Economia de Diesel e Combustível | Ecodiffusore Brasil',
            'seoDescription' => 'Artigos sobre economia de diesel, redução de consumo de combustível e tecnologia para caminhões, máquinas agrícolas e geradores.',
            'seoPath' => '/blog',
        ], 'site');
    }

    public function show(string $slug): void
    {
        $post = BlogPosts::find($slug);
        if (!$post) {
            Router::redirect('/blog');
        }

        $baseUrl = rtrim(Config::get('app_url', 'https://ecodiffusorebrasil.com.br'), '/');

        View::render('site/blog/' . $slug, [
            'post' => $post,
            'seoTitle' => $post['title'] . ' | Ecodiffusore Brasil',
            'seoDescription' => $post['description'],
            'seoPath' => '/blog/' . $slug,
            'seoImage' => !empty($post['image']) ? $baseUrl . View::asset($post['image']) : null,
        ], 'site');
    }
}
