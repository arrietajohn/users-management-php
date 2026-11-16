<?php

declare(strict_types=1);

final class WebRoutes
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function routes(): array
    {
        return array(
            'home' => array(
                'method' => 'GET',
                'action' => 'home',
            ),
            'users.create' => array(
                'method' => 'GET',
                'action' => 'create',
            ),
            'users.store' => array(
                'method' => 'POST',
                'action' => 'store',
            ),
            'users.index' => array(
                'method' => 'GET',
                'action' => 'index',
            ),
            'users.show' => array(
                'method' => 'GET',
                'action' => 'show',
            ),
            'users.edit' => array(
                'method' => 'GET',
                'action' => 'edit',
            ),
            'users.update' => array(
                'method' => 'POST',
                'action' => 'update',
            ),
            'users.delete' => array(
                'method' => 'POST',
                'action' => 'delete',
            ),
            'celulares.index' => array('method' => 'GET', 'action' => 'celulares.index'),
            'celulares.create' => array('method' => 'GET', 'action' => 'celulares.create'),
            'celulares.store' => array('method' => 'POST', 'action' => 'celulares.store'),
            'celulares.show' => array('method' => 'GET', 'action' => 'celulares.show'),
            'celulares.edit' => array('method' => 'GET', 'action' => 'celulares.edit'),
            'celulares.update' => array('method' => 'POST', 'action' => 'celulares.update'),
            'celulares.delete' => array('method' => 'POST', 'action' => 'celulares.delete'),
            'auth.login' => array(
                'method' => 'GET',
                'action' => 'login',
            ),
            'auth.authenticate' => array(
                'method' => 'POST',
                'action' => 'authenticate',
            ),
            'auth.logout' => array(
                'method' => 'POST',
                'action' => 'logout',
            ),
            'auth.forgot' => array(
                'method' => 'GET',
                'action' => 'forgot',
            ),
            'auth.forgot.send' => array(
                'method' => 'POST',
                'action' => 'forgot.send',
            ),
            'auth.reset' => array(
                'method' => 'GET',
                'action' => 'reset',
            ),
            'auth.reset.submit' => array(
                'method' => 'POST',
                'action' => 'reset.submit',
            ),
        );
    }
}
