# ERROR REPORT: Mixed-Content and CORS Failures in GitHub Codespaces

## Summary
While running the Gondwana Rates UI inside GitHub Codespaces, the browser repeatedly blocked API calls because:
- The frontend was served over HTTPS (`https://<workspace>-5500.app.github.dev`) but attempted to probe the backend over HTTP (`http://…:8000/api`), triggering mixed-content protection.
- The backend tunnel at port 8000 was still private, so the Codespaces proxy returned 401 responses without the `Access-Control-Allow-Origin` header, causing persistent CORS failures.

## Timeline
1. **Initial Issue**  
   - Mixed-content warnings when the frontend fetched `http://…:8000/api/*`.  
   - Requests were blocked before reaching the backend.

2. **Frontend Update** (`frontend/app.js`)  
   - Added sanitisation for API candidates: enforce HTTPS for Codespaces hostnames, strip trailing slashes, and ignore insecure options when the page is HTTPS.  
   - Implemented diagnostic logging for API base selection.

3. **Validation Attempt**  
   - After switching candidates to HTTPS, the browser still showed CORS errors (`No 'Access-Control-Allow-Origin' header`).  
   - Root cause: Codespaces requires the backend port to be explicitly published (private ports return 401 without CORS headers).

4. **Resolution**  
   - Published port 8000 to the public visibility scope using:  
     `gh codespace ports visibility 8000:public -c $CODESPACE_NAME`  
   - Reloaded the frontend and confirmed `/api/test` and `/api/rates` succeed with proper CORS headers.

## Files Modified
- [`frontend/app.js`](frontend/app.js:1) – HTTPS-aware API base selection and logging.
- [`README.md`](README.md:51) – Documentation for Codespaces HTTPS behaviour and override instructions.

## Key Lessons / Recommendations
1. **Always Use HTTPS in Codespaces**  
   The runtime enforces HTTPS, so API discovery must upgrade any matching `*.app.github.dev` host to HTTPS.

2. **Publish Backend Ports**  
   Before testing cross-origin requests, ensure the backend port is set to `public` (or at least available to the browser session). Use `gh codespace ports list` to confirm status.

3. **Leverage Logging**  
   The frontend now emits `[api-base]` logs that reveal candidate URLs, probe outcomes, and fetch attempts, making it easier to diagnose future regressions.

4. **Persisted API Base**  
   Clearing or sanitising `localStorage['API_BASE']` prevents stale HTTP values from reintroducing the issue when the page reloads.

## Future Considerations
- Automate port publication via project setup scripts or README instructions.  
- Consider server-side detection to inject the correct API base or provide a debug panel showing current connectivity state.  
- Monitor Codespaces policy changes; the proxy requires authentication for private tunnels and may change header behaviour over time.