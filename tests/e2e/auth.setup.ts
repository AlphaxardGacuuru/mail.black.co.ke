import { execSync } from "node:child_process"
import { expect, test as setup } from "@playwright/test"

export const E2E_EMAIL = process.env.E2E_EMAIL ?? "e2e@example.test"
export const E2E_PASSWORD = process.env.E2E_PASSWORD ?? "e2e-smoke-password"

// Locally artisan runs through Sail; CI sets E2E_ARTISAN="php artisan".
const ARTISAN = process.env.E2E_ARTISAN ?? "./vendor/bin/sail artisan"

setup("signs in the smoke test user", async ({ page }) => {
	// Booting tinker through Sail alone can take half a minute.
	setup.setTimeout(120_000)

	// Creates the user, or resets its password, so a fresh database works too.
	// forceFill because email_verified_at isn't mass assignable.
	execSync(
		`${ARTISAN} tinker --execute '${[
			"App\\Models\\User::query()",
			`->firstOrNew(["email" => "${E2E_EMAIL}"])`,
			"->forceFill([",
			'"name" => "E2E Smoke Test",',
			'"email_verified_at" => now(),',
			`"password" => Illuminate\\Support\\Facades\\Hash::make("${E2E_PASSWORD}"),`,
			"])",
			"->save();",
		].join(" ")}'`,
		{ stdio: "inherit" }
	)

	await page.goto("/login")
	await page
		.locator('input[name="email"]')
		.fill(E2E_EMAIL)
	await page
		.locator('input[name="password"]')
		.fill(E2E_PASSWORD)
	await page
		.getByRole("button", { name: /log in/i })
		.click()

	// The app keeps the Sanctum token in localStorage, which storageState saves.
	await expect(page).toHaveURL(/\/mail$/, { timeout: 15_000 })
	await expect
		.poll(() => page.evaluate(() => localStorage.getItem("sanctumToken")))
		.toBeTruthy()

	await page
		.context()
		.storageState({ path: "tests/e2e/.auth/user.json" })
})
