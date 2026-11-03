# Live artifact and workspace verification

Verified the local HTTPS application at https://framework-cloud.test using real R and Python client uploads, a rendered Quarto book, and browser interactions. HTTP requests reached the running Laravel app and persisted real versions and files. TLS verification remained enabled with the local Herd CA.

## Retained examples

Project: https://framework-cloud.test/projects/live-artifacts-20261102-215349

Report: https://framework-cloud.test/r/jndutvltbi45ml7imhsi6fhe

Quarto book: https://framework-cloud.test/d/o1zexirsasenq5xkwg7auiif

The examples use public mtcars data and synthetic dose-response data. The temporary project token was revoked after verification.

## Checked behavior

- Published an R data frame, lm model, and ggplot through the R package. Published a pandas data frame, statsmodels OLS model, and seaborn grid through the Python package.
- Checked actual reader pages, coefficient tables and interval plots, image loading, typed columns, nulls, dates, booleans, and exact 64-bit identifiers. Exercised numeric sorting, literal-text search, and table pagination in the browser.
- Rendered a real two-chapter Quarto book and uploaded its output directory through Python. Checked styles, figure, scripts, fonts, and a search query that returned the methods chapter.
- Found and fixed a real 404 when Quarto's home link used a directory URL. Signed content routes now accept a missing path and redirect root and nested directories to their HTML entry page after capability verification. Added root/nested/missing/expired/invalid capability regressions and verified the uploaded book's home URL in the browser.
- Created and published a report through the browser, including text and three real artifacts. Verified the published reader and its three embedded readers as owner and guest.
- Uploaded a PNG through the browser using the existing artifact name, creating version 2. Checked the password page in an incognito session, raw-file HTTP 403 before unlock, and a successfully loaded 1200-pixel image after unlock. Restored the example figure's public sharing and removed its test password through the settings UI after verification.
- Published a second table version through Python containing a distinct marker. The stable artifact URL displayed version 2 while the existing report continued to embed version 1 and its original 120 rows and exact identifier.
- Confirmed a report hides an artifact when the source changes from public to unlisted without explicit unlisted sharing consent. Restoring public visibility restored the pinned figure.
- Checked the signed-in /projects overview with real data. Recent artifacts, reports, and collections precede the full project list. Added owner isolation, deleted-project exclusion, ordering, and result-limit tests. Updated sidebar labels to Your research and Published outputs.

## Validation and limits

The final focused Cloud run passed 30 tests with 343 assertions. Pint and the production frontend build passed. The build retains the existing warning about the main JavaScript chunk exceeding 500 kB.

These checks cover the local app, not a production deployment. Optional R table packages were not installed for this live run. Quarto's own resources loaded; Herd's injected development instrumentation generated sandbox/CORS console noise. Browser iframe clicks were unreliable in the preview tool, so the directory-link regression was also reproduced and verified by navigating its observed URL directly.
