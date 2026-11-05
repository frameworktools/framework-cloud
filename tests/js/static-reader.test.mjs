import test from 'node:test';
import assert from 'node:assert/strict';
import { safeArtifactUrl } from '../../resources/js/static-artifact-reader.js';
test('reader resource links reject scripts, traversal, controls, and protocol-relative URLs', () => {
 for (const value of ['javascript:alert(1)','data:text/html,<script>','//evil.test/x','../secret','x/../secret','https://x.test/\nfoo','\\evil\\x']) assert.equal(safeArtifactUrl(value),null,value);
 for (const value of ['figure.svg','report files/index.html','./report:2026.html','https://example.org/files/report.pdf','/d/key/raw']) assert.equal(safeArtifactUrl(value),value);
});
