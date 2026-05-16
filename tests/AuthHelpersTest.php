<?php

use PHPUnit\Framework\TestCase;

final class AuthHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['USER']);
        unset($_SESSION['CSRF_TOKEN']);
    }

    public function testCsrfTokenIsGeneratedAndReused(): void
    {
        $token = csrf_token();

        $this->assertNotEmpty($token);
        $this->assertSame($token, csrf_token());
        $this->assertTrue(csrf_token_is_valid($token));
    }

    public function testCsrfTokenRejectsMissingOrWrongToken(): void
    {
        csrf_token();

        $this->assertFalse(csrf_token_is_valid(null));
        $this->assertFalse(csrf_token_is_valid('wrong-token'));
    }

    public function testCsrfFieldRendersHiddenInput(): void
    {
        $field = csrf_field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString(csrf_token(), $field);
    }

    public function testCurrentUserHelpersReturnSessionUserDetails(): void
    {
        $_SESSION['USER'] = (object)[
            'id' => 42,
            'role' => 'staff',
        ];

        $this->assertSame(42, current_user_id());
        $this->assertSame('staff', current_user_role());
        $this->assertSame($_SESSION['USER'], current_user());
    }

    public function testGuestRoleIsReturnedWithoutSessionUser(): void
    {
        $this->assertNull(current_user_id());
        $this->assertSame('guest', current_user_role());
    }

    public function testHasRoleAcceptsSingleRoleOrRoleList(): void
    {
        $staff = (object)['role' => 'staff'];

        $this->assertTrue(has_role('staff', $staff));
        $this->assertTrue(has_role(['staff', 'admin'], $staff));
        $this->assertFalse(has_role('admin', $staff));
    }

    public function testStaffOrAdminHelperMatchesOnlyOperationalRoles(): void
    {
        $this->assertFalse(is_staff_or_admin((object)['role' => 'user']));
        $this->assertTrue(is_staff_or_admin((object)['role' => 'staff']));
        $this->assertTrue(is_staff_or_admin((object)['role' => 'admin']));
    }

    public function testAdminHelperMatchesOnlyAdminRole(): void
    {
        $this->assertFalse(is_admin((object)['role' => 'staff']));
        $this->assertTrue(is_admin((object)['role' => 'admin']));
    }

    public function testStaffAndAdminCanAccessAnyTicket(): void
    {
        $ticket = (object)['user_id' => 10];

        $this->assertTrue(can_access_ticket($ticket, (object)['id' => 20, 'role' => 'staff']));
        $this->assertTrue(can_access_ticket($ticket, (object)['id' => 30, 'role' => 'admin']));
    }

    public function testNormalUserCanOnlyAccessOwnTicket(): void
    {
        $ticket = (object)['user_id' => 10];

        $this->assertTrue(can_access_ticket($ticket, (object)['id' => 10, 'role' => 'user']));
        $this->assertFalse(can_access_ticket($ticket, (object)['id' => 11, 'role' => 'user']));
    }

    public function testFinalActiveAdminIsProtected(): void
    {
        $activeAdmin = (object)['role' => 'admin', 'is_active' => 1];
        $inactiveAdmin = (object)['role' => 'admin', 'is_active' => 0];
        $staff = (object)['role' => 'staff', 'is_active' => 1];

        $this->assertTrue(is_final_active_admin($activeAdmin, 1));
        $this->assertFalse(is_final_active_admin($activeAdmin, 2));
        $this->assertFalse(is_final_active_admin($inactiveAdmin, 1));
        $this->assertFalse(is_final_active_admin($staff, 1));
    }

    public function testPasswordResetRequiredDetectsTemporaryPasswordState(): void
    {
        $this->assertTrue(password_reset_required((object)['must_reset_password' => 1]));
        $this->assertFalse(password_reset_required((object)['must_reset_password' => 0]));
        $this->assertFalse(password_reset_required(null));
    }

    public function testOnlyLocalUsersCanChangePasswordInApplication(): void
    {
        $this->assertTrue(can_change_local_password((object)['auth_provider' => 'local']));
        $this->assertTrue(can_change_local_password((object)[]));
        $this->assertFalse(can_change_local_password((object)['auth_provider' => 'ldap']));
    }

    public function testForcedPasswordResetAllowsOnlyPasswordAndLogoutRoutes(): void
    {
        $this->assertTrue(is_password_reset_allowed_route('Password'));
        $this->assertTrue(is_password_reset_allowed_route('logout'));
        $this->assertFalse(is_password_reset_allowed_route('Home'));
        $this->assertFalse(is_password_reset_allowed_route('Users'));
    }
}
