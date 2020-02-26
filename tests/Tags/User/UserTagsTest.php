<?php

namespace Tests\Tags\User;

use Statamic\Facades\Parse;
use Statamic\Facades\User;
use Statamic\Facades\UserGroup;
use Tests\FakesRoles;
use Tests\FakesUserGroups;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class RegisterFormTest extends TestCase
{
    use FakesRoles,
        FakesUserGroups,
        PreventSavingStacheItemsToDisk;

    private function tag($tag)
    {
        return Parse::template($tag, []);
    }

    /** @test */
    function it_renders_user_can_tag_content()
    {
        $this->setTestRoles([
            'webmaster' => ['super'],
            'admin' => ['access cp', 'configure collections'],
            'author' => ['access cp'],
        ]);

        $this->actingAs(User::make()->assignRole('webmaster')->save());

        $this->assertEquals('yes', $this->tag('{{ user:can do="configure collections" }}yes{{ /user:can }}'));
        $this->assertEquals('', $this->tag('{{ user:cant do="configure collections" }}yes{{ /user:cant }}'));

        $this->actingAs(User::make()->assignRole('admin')->save());

        $this->assertEquals('yes', $this->tag('{{ user:can do="configure collections" }}yes{{ /user:can }}'));
        $this->assertEquals('', $this->tag('{{ user:cant do="configure collections" }}yes{{ /user:cant }}'));

        $this->actingAs(User::make()->assignRole('author')->save());

        $this->assertEquals('', $this->tag('{{ user:can do="configure collections" }}yes{{ /user:can }}'));
        $this->assertEquals('yes', $this->tag('{{ user:cant do="configure collections" }}yes{{ /user:cant }}'));

        // Test if user has any of these permissions
        $this->assertEquals('yes', $this->tag('{{ user:can do="access cp|configure collections" }}yes{{ /user:can }}'));
        $this->assertEquals('', $this->tag('{{ user:cant do="access cp|configure collections" }}yes{{ /user:cant }}'));
    }

    /** @test */
    function it_renders_user_is_tag_content()
    {
        $this->setTestRoles([
            'webmaster' => ['super'], // Though super users have permission to do everything, they do not inherit all roles
            'admin',
        ]);

        $this->actingAs(User::make()->assignRole('webmaster')->save());

        $this->assertEquals('yes', $this->tag('{{ user:is role="webmaster" }}yes{{ /user:is }}'));
        $this->assertEquals('', $this->tag('{{ user:is role="admin" }}yes{{ /user:is }}'));
        $this->assertEquals('', $this->tag('{{ user:isnt role="webmaster" }}yes{{ /user:isnt }}'));
        $this->assertEquals('yes', $this->tag('{{ user:isnt role="admin" }}yes{{ /user:isnt }}'));

        // Test if user is assigned any of these roles
        $this->assertEquals('yes', $this->tag('{{ user:is role="webmaster|admin" }}yes{{ /user:is }}'));
        $this->assertEquals('', $this->tag('{{ user:isnt role="webmaster|admin" }}yes{{ /user:isnt }}'));
    }

    /** @test */
    function it_renders_user_in_tag_content()
    {
        $this->setTestRoles([
            'webmaster' => ['super'],
        ]);

        $this->setTestUserGroups([
            'favourite' => ['webmaster'], // Though super users have permission to do everything, they do not inherit all groups
            'non_favourite',
        ]);

        $this->actingAs(User::make()->addToGroup('favourite')->save());

        $this->assertEquals('yes', $this->tag('{{ user:in group="favourite" }}yes{{ /user:in }}'));
        $this->assertEquals('', $this->tag('{{ user:in group="non_favourite" }}yes{{ /user:in }}'));
        $this->assertEquals('', $this->tag('{{ user:not_in group="favourite" }}yes{{ /user:not_in }}'));
        $this->assertEquals('yes', $this->tag('{{ user:not_in group="non_favourite" }}yes{{ /user:not_in }}'));

        // Test if user is in any of these groups
        $this->assertEquals('yes', $this->tag('{{ user:in group="favourite|non_favourite" }}yes{{ /user:in }}'));
        $this->assertEquals('', $this->tag('{{ user:not_in group="favourite|non_favourite" }}yes{{ /user:not_in }}'));
    }
}
