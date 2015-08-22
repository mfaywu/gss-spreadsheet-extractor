=== Plugin Name ===
Contributors: abasoukos, meitar
Tags: Google Docs, Google, Spreadsheet, shortcode
Requires at least: 4.2
Tested up to: 4.3
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Introduces shortcodes that extract fields from a public Google Spreadsheet and display them in a post or page.

== Description ==

This plugin adds shortcodes for accessing Google Spreadsheets that allow you to:
* access and display fields from the spreadsheet.
* conditionally display sections depending on values from the spreadsheet.
* iterate over all the (optionally sorted and filtered) rows of the spreadsheet, rendering a template for each row.     

It has originally been developed to help create self-service registration via Google Forms, but it's useful in
other cases.

Keep in mind that you will need to keep a close eye on the data in the spreadsheet; if they can be submitted freely there may still be XSS attacks.
Further, it's not very optimised and requires the spreadsheet to be publicly shared. If you need more fine-grained control this plugin isn't that well-suited for it.

Pull requests for fixes are welcome.

Inspired by an early version of inline-google-spreadsheet-viewer at http://maymay.net/blog/projects/inline-google-spreadsheet-viewer/.

== Usage ==

0. Prepare the spreadsheet:
* Open your spreadsheet in Google Drive and make it publicly accessible
* When viewing your spreadsheet, select File->Publish to the Web...
* In the pop-up dialog, select "Link", choose the sheet you want and select "Comma-separated values (.csv)".
* Click Publish and copy the link that is produced.
* It's going to be a link of the form "https://docs.google.com/spreadsheets/d/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX/pub?gid=0&single=true&output=csv"
* Your key is whatever corresponds to the X part, your sheet id is the number after "gid=".
  
1. Edit the page / post that you want to add values from the spreadsheet and add a gss_load shortcode before any other gss shortcode:

[gss_load key="" gid="" use_cache="" stale_in="" expires_in="" strip="" collate="" sortcolumn=""]

Where:
* key and gid are the key and gid from the spreadsheet URL
* (optional) if use_cache is 'no', the spreadsheet will be re-parsed on each request.
* (optional) stale_in is the minimum number of seconds that need to pass before the spreadsheet is considered "stale" and will get re-retrieved from Google. The default is 60.
* (optional) expires_in is the maximum number of seconds that a spreadsheet from google will be retained; after this period,it will be retrieved and reparsed. The default is 3600.
* (optional) strip is the number of rows that will get stripped off of the start of the spreadsheet; the default is 1, which strips off the default header.
* (optional) collate is the default sort collation, the default is "en_US".
* (optional) sortcolumn is the 1-based column that will act as the sort key when loading data. Please note that this sort is unstable, meaning that new elements added at the end of the spreadsheet may cause other row IDs to change.

After this is inserted, you can use the following sortcodes

2. gss_repeat allows you to repeat the shortcode content for each row in the spreadsheet.

Each row has a row id, which is the number of the row from the gss_load shortcode. gss_repeat can sort and filter the rows from the spreadsheet,
and the row ids remain stable across row additions / modifications to the spreadsheet; row deletions will cause row IDs to be rearranged, so you might want simply
erase the data from the row and add a validColumn to the shortcode. For example, the following will create a list with an element for each row in the spreadsheet:

    <ul>
    [gss_repeat sortcolumn="" collate="" rowid="" validcolumn="" validcondition=""]
	  <li>This is row #__ROWID__</li>
    [/gss_repeat]
    </ul>

Alla parameters are optional, with sensible defaults:
* sortcolumn: if present, the 1-based column that should be sorted on
* collate (default en_us): the collation used to perform the sort.
* validcolumn: if present, the column that defines whether the row is valid and should be shown or invalid and should be skipped.
* validcondition: if missing, the validcolumn should be non-empty; if present, the validcolumn should be equal to this value to be considered valid.
* rowid (default __ROWID__): where present in the  shortcode content, this text will get replaced with the row id.

3. gss_cell allows you to get cell values out of the spreadsheet. 

Expressed as [gss_cell row="" column=""], only the column attribute is mandatory; if the row is missing, then either the query parameter 'rid' will be used 
or the row from an eclosing gss_repeat will be used. This allows loops to be used like this:

    <ul>
    [gss_repeat ...]
	     <li><a href="<some-page-url>?rid=__ROWID__">[gss_cell column="5"]</a></li>
    [/gss_repeat]
    </ul>
 
The page pointed to by the link can also use constructs like [gss_cell column="5"] (after loading the spreadsheet with the exact same gss_load) and the row id
will be consistent between the originating page and the target page.

4. Create links with gss_link

Wordpress does not allow shortcodes in HTML attributes, so gss_link is a workaround: [gss_link column="23" tag="img" attr="src" /] creates an img tag 
with an src attribute that has the value of the current row, column 23 from the loaded spreadsheet. The parameters are:
* row: The row to use; if omitted then normal row resolution is performed as per the gss_cell entry.
* column: mandatory, the column that has the well-formed link to the image.
* tag (default "a"): the tag name to use when creating a link.
* attr (default "href"): the attribute to use when creating a link.
 
Keep in mind that this shortcode can have content: f.e. [gss_link column=10][gss_cell column=11][/gss_link] will create a link to the 10th column, with text
taken from the 11th.

5. Conditional rendering with gss_if

Sometimes you need to create a link or show an image only when a column is present in a row; there you use gss_if, which will render the contents only if 
a column is nonempty (or multiple columns are nonempty). Parameters:
* row: The row to use; if omitted then normal row resolution is performed as per the gss_cell entry.
* column: the column to check for a value
* columns: a coma-separated list of column numbers
The content is rendered only if all cells referenced (row x (column + columns)) are non-empty.
