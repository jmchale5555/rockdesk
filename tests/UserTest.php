<?php

use Model\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUsernameIsNormalizedForLogin(): void
    {
        $user = new User;

        $this->assertSame('j.smith', $user->normalizeUsername('  J.Smith  '));
    }

    public function testUsernameAcceptsActiveDirectoryFriendlyCharacters(): void
    {
        $user = new User;

        $this->assertTrue($user->isValidUsername('john.smith'));
        $this->assertTrue($user->isValidUsername('john-smith'));
        $this->assertTrue($user->isValidUsername('john_smith'));
    }

    public function testUsernameRejectsUnsafeOrTooShortValues(): void
    {
        $user = new User;

        $this->assertFalse($user->isValidUsername('js'));
        $this->assertFalse($user->isValidUsername('john smith'));
        $this->assertFalse($user->isValidUsername('john@example.com'));
    }

    public function testSuggestUsernameFromEmailUsesSafeLocalPart(): void
    {
        $user = new User;

        $this->assertSame('jane.smith', $user->suggestUsernameFromEmail('Jane Smith@example.com'));
        $this->assertSame('support.user', $user->suggestUsernameFromEmail('support+user@example.com'));
        $this->assertSame('user', $user->suggestUsernameFromEmail(''));
    }

    public function testOnlyKnownRolesAreValid(): void
    {
        $user = new User;

        $this->assertTrue($user->isValidRole('user'));
        $this->assertTrue($user->isValidRole('staff'));
        $this->assertTrue($user->isValidRole('admin'));
        $this->assertFalse($user->isValidRole('superadmin'));
    }

    public function testOnlyKnownAuthProvidersAreValid(): void
    {
        $user = new User;

        $this->assertTrue($user->isValidAuthProvider('local'));
        $this->assertTrue($user->isValidAuthProvider('ldap'));
        $this->assertFalse($user->isValidAuthProvider('oauth'));
    }

    public function testAdminCreateRequiresTemporaryPassword(): void
    {
        $user = new User;

        $this->assertFalse($user->validateAdminCreate([
            'name' => 'Jane Smith',
            'username' => 'jane.smith',
            'email' => 'jane@example.com',
            'role' => 'user',
            'is_active' => 1,
            'password' => '',
        ]));
        $this->assertArrayHasKey('password', $user->errors);
    }

    public function testAdminCreateAcceptsValidUserData(): void
    {
        $user = new User;

        $this->assertTrue($user->validateAdminCreate([
            'name' => 'Jane Smith',
            'username' => 'jane.smith',
            'email' => 'jane@example.com',
            'role' => 'staff',
            'is_active' => 1,
            'password' => 'temporary-password',
        ]));
    }

    public function testAdminUpdateDoesNotRequirePassword(): void
    {
        $user = new User;

        $this->assertTrue($user->validateAdminUpdate([
            'name' => 'Jane Smith',
            'username' => 'jane.smith',
            'email' => '',
            'role' => 'user',
            'is_active' => 1,
            'password' => '',
        ]));
    }

    public function testAdminUpdateRejectsInvalidRoleAndEmail(): void
    {
        $user = new User;

        $this->assertFalse($user->validateAdminUpdate([
            'name' => 'Jane Smith',
            'username' => 'jane.smith',
            'email' => 'not-an-email',
            'role' => 'owner',
            'is_active' => 1,
            'password' => '',
        ]));
        $this->assertArrayHasKey('email', $user->errors);
        $this->assertArrayHasKey('role', $user->errors);
    }

    public function testAdminValidationRejectsOverlongNameAndEmail(): void
    {
        $user = new User;

        $this->assertFalse($user->validateAdminUpdate([
            'name' => str_repeat('a', 121),
            'username' => 'jane.smith',
            'email' => str_repeat('a', 180) . '@example.com',
            'role' => 'user',
            'is_active' => 1,
            'password' => '',
        ]));
        $this->assertArrayHasKey('name', $user->errors);
        $this->assertArrayHasKey('email', $user->errors);
    }
}
