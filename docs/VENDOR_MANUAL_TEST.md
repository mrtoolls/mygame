# Coin Vendor manual acceptance script

Use a staging copy with test accounts and a non-production payment channel. Do not upload real identity documents to staging.

## Accounts and setup

- Create an applicant with verified email and phone, an account older than the configured minimum, and a country where Coin Vendors are enabled.
- Create a separate buyer account, a second vendor account, an account with an unsupported country, and an admin with and without the vendor identity-review permission.
- Configure the vendor bond, fee, rate band, limits, cooldown, upload limits, and rejected-file retention period.
- Confirm PHP `upload_max_filesize` and `post_max_size` exceed the configured maximum identity image/video sizes, accounting for multipart overhead.
- Ensure the video challenge can be generated and that the staging server's file storage is private.

## Desktop browser

1. Apply as a vendor. Check required fields, validation, verified email/phone and country/account-age gates.
2. Upload an allowed ID image and complete the live video challenge. Confirm a plain file upload, expired challenge, replayed challenge, wrong account, wrong session, oversized video, HTML/SVG masquerade, and double extension are rejected.
3. Confirm ID metadata is stripped, the ID is not present in public storage or public URLs, and the application status/reviewer fields cannot be set by the applicant.
4. As an admin without identity permission, try to view the identity file and confirm 403. Repeat with the permitted admin; confirm it succeeds and writes an audit entry for each view.
5. Approve the application. Confirm the bond is held separately from trade escrow, the vendor gets a notice, and the event is audited.
6. Check the directory: only active, selling, sufficiently funded vendors within their limits and the selected market are shown. Confirm agents and vendors share one list and vendors have a Vendor label.
7. Start vendor-to-user and user-to-vendor trades. Confirm the displayed rate stays locked, fee and escrow values are correct, and neither participant can spend held trade coins or bond in transfers, gifts, games, competitions, or marketplace listings.
8. Try self-trading and a linked account; both should be blocked. Double-click a listing and create concurrent buyer attempts; verify limits prevent overselling and the same request does not duplicate escrow.
9. Open a dispute. Confirm no automatic escrow release. Resolve it, add a penalty, and verify the bond is consumed first only up to its available amount.
10. Suspend a vendor with an open trade. Confirm new sales stop while the open trade can still resolve. Exercise reject (reason required), request more information, revoke, reinstate, and resignation; verify notifications and audit entries.
11. Attempt to release the bond before cooldown, with an open/disputed trade, and with unresolved penalties; all must be blocked. After cooldown and clearance, release with an admin reason and repeat the same idempotency request; only one release should post.
12. Run reconciliation and verify vendor bond and trade escrow reconcile independently.
13. For a rejected applicant, submit a data-deletion request, run cleanup twice, and verify private files and application PII are removed while audit records remain.

## Phone and video capture

Repeat steps 1-4 on a real iPhone using iOS Safari and a real Android device using Chrome. Grant camera permission, capture the prompted phrase/code in one continuous recording, submit, and verify the preview and upload state. Test camera denial, switching away during recording, network interruption, expired challenge, and retry with a newly issued challenge. Confirm the camera UI fits the viewport and the recording does not enter a public gallery/cache.

## Release checks

- Check browser and server logs for identity numbers, identity file paths, and full challenge/video content; none should be logged.
- Run the Coin Vendor integration script and then the full repository PHP integration suite.
- Check admin and applicant pages at desktop and narrow mobile widths.
- Record the tested PHP SAPI limits and whether `ffprobe` is installed on the actual production host; CLI values alone do not confirm cPanel PHP-FPM/Apache settings.
