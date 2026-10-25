# dromos.codenexis.com

The Dromos website: one static page with no build step. Upload the contents of this directory, including `.htaccess`, to the web root. Leave out this README.

- `index.html` holds the page, its metadata and its structured data.
- `assets/site.js` holds the benchmark figures the race switcher shows. Update them, the table and the FAQ in `index.html`, and `llms.txt` together when a new dataset is published.
- When `assets/site.css` or `assets/site.js` changes, bump the `?v=` query in `index.html` and `404.html`, because both are cached for a year.
- `sitemap.xml` has the date of the last change; update it with the page.
