<!--
Title: Stock Photos
Slug: stock-photos
Parent: Image Generator Documentation (https://beautifulplugins.com/docs/image-generator/)
Permalink: https://beautifulplugins.com/docs/image-generator/stock-photos/
SEO Title: Free Stock Photos in WordPress from Unsplash, Pexels & Pixabay — Image Generator
Meta Description: Search and insert free Unsplash, Pexels and Pixabay stock photos in the WordPress block editor and Media Library, with photographer credit — or use them as automatic featured images.
Focus Keyword: Unsplash Pexels Pixabay WordPress plugin
-->

# Stock Photos

Search millions of free photos from **Unsplash**, **Pexels** and **Pixabay** without leaving WordPress. Insert them in the editor, use them as automatic featured images, or put them under your own design in a [photo template](https://beautifulplugins.com/docs/image-generator/photo-templates/).

## Getting free API keys

Each library needs its own free key. You only need one, but you can add all three.

| Library | Where to get a key | Notes |
|---|---|---|
| **Unsplash** | [unsplash.com/oauth/applications](https://unsplash.com/oauth/applications) — create an app and copy its **Access Key** | New keys are limited to 50 searches an hour until Unsplash approves the app for production. |
| **Pexels** | [pexels.com/api/new](https://www.pexels.com/api/new/) | |
| **Pixabay** | [pixabay.com/api/docs](https://pixabay.com/api/docs/) — your key is shown once you're signed in | Search results are cached for 24 hours, as Pixabay requires. |

Paste the key under **Image Generator → Settings → Stock Photos** and click **Test Connection**. Or define it in `wp-config.php`:

```php
define( 'AIMG_UNSPLASH_KEY', 'your-access-key' );
define( 'AIMG_PEXELS_KEY', 'your-key' );
define( 'AIMG_PIXABAY_KEY', 'your-key' );
```

## Searching and inserting

Open the image modal from the ✨ block button, the **AI Featured Image** panel, or **Media → Library → Generate Image**, and choose the **Unsplash**, **Pexels** or **Pixabay** tab:

1. The search is filled in with keywords from the post title — type your own to change it.
2. Filter by **Orientation** (landscape, portrait, square) and **Color**.
3. Scroll and click **Load more** for more results.
4. Pick a photo and click **Insert Photo**.

The photo is downloaded into your Media Library with alt text and the photographer's credit, then inserted into the block or set as the featured image. Importing the same photo again reuses the copy already in your library.

## Automatic stock featured images

Under **Settings → Automatic Featured Images → Generate With**, choose **Stock photo** or **Stock photo, template if none is found**. When a post is saved without a featured image, a photo is found in the background with keywords from the post title. Then set:

- **Library for Automatic Images** — which library to use, or *The first library with a key*.
- **Photo Orientation** — Landscape (default), Portrait or Square.
- **Import Size** — Regular (about 1080–1900 px wide), Large (up to 2400 px wide, default) or Original (largest file).

Want the photo to carry your logo and title? Use a [photo template](https://beautifulplugins.com/docs/image-generator/photo-templates/) with the **Image template** method instead.

## Photographer credit

Unsplash, Pexels and Pixabay photos are free to use, and the libraries ask you to credit photographers where you can. With **Credit** ticked (the default), the plugin adds a credit with links to the image caption, which most themes show under the image. The credit is always saved with the image, even when this is off.

## Privacy

Searches send only the search terms (what you type, or keywords from the post title) and your API key to the library you use. Thumbnails in search results load from the library's servers in the editor, as the libraries require; the photo you pick is downloaded to your own Media Library, so visitors never load images from the library. Nothing is sent until you add a key and search. Photos are only downloaded from each library's own image servers.

See each library's terms: Unsplash ([terms](https://unsplash.com/terms), [API guidelines](https://help.unsplash.com/en/articles/2511245-unsplash-api-guidelines)), Pexels ([terms](https://www.pexels.com/terms-of-service/), [license](https://www.pexels.com/license/)), Pixabay ([terms](https://pixabay.com/service/terms/), [license](https://pixabay.com/service/license-summary/)).

## Related

- [Photo Templates](https://beautifulplugins.com/docs/image-generator/photo-templates/)
- [Automatic Featured Images](https://beautifulplugins.com/docs/image-generator/automatic-featured-images/)
