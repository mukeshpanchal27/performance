# [Optimization Detective](https://wordpress.org/plugins/optimization-detective/)

![Performance Lab plugin banner with icon](https://github.com/WordPress/performance/assets/10103365/99d37ba5-27e3-47ea-8ab8-48de75ee69bf)

Provides an API for leveraging real user metrics to detect optimizations to apply on the frontend to improve page performance.

## Description

This plugin captures real user metrics about what elements are displayed on the page across a variety of device form factors (e.g. desktop, tablet, and phone) in order to apply loading optimizations which are not possible with WordPress’s current server-side heuristics.

This plugin is a dependency which does not provide end-user functionality on its own. For that, please install the dependent plugin [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) or [Embed Optimizer](https://wordpress.org/plugins/embed-optimizer/) (among [others](https://github.com/WordPress/performance/labels/%5BPlugin%5D%20Optimization%20Detective) to come from the WordPress Core Performance team). There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

Your site must have the **REST API accessible** to unauthenticated frontend visitors since this is how metrics are collected about how a page should be optimized.

## Background

WordPress uses [server-side heuristics](https://make.wordpress.org/core/2023/07/13/image-performance-enhancements-in-wordpress-6-3/) to make educated guesses about which images are likely to be in the initial viewport. Likewise, it uses server-side heuristics to identify a hero image which is likely to be the Largest Contentful Paint (LCP) element. To optimize page loading, it avoids lazy loading any of these images while also adding `fetchpriority=high` to the hero image. When these heuristics are applied successfully, the LCP metric for page loading can be improved 5-10%. Unfortunately, however, there are limitations to the heuristics that make the correct identification of which image is the LCP element only about 50% effective. See [Analyzing the Core Web Vitals performance impact of WordPress 6.3 in the field](https://make.wordpress.org/core/2023/09/19/analyzing-the-core-web-vitals-performance-impact-of-wordpress-6-3-in-the-field/). For example, it is [common](https://github.com/GoogleChromeLabs/wpp-research/pull/73) for the LCP element to vary between different viewport widths, such as desktop versus mobile. Since WordPress's heuristics are completely server-side it has no knowledge of how the page is actually laid out, and it cannot prioritize loading of images according to the client's viewport width.

In order to increase the accuracy of identifying the LCP element, including across various client viewport widths, this plugin gathers metrics from real users (RUM) to detect the actual LCP element and then use this information to optimize the page for future visitors so that the loading of the LCP element is properly prioritized. This is the purpose of Optimization Detective. The approach is heavily inspired by Philip Walton’s [Dynamic LCP Priority: Learning from Past Visits](https://philipwalton.com/articles/dynamic-lcp-priority/). See also the initial exploration document that laid out this project: [Image Loading Optimization via Client-side Detection](https://docs.google.com/document/u/1/d/16qAJ7I_ljhEdx2Cn2VlK7IkiixobY9zNn8FXxN9T9Ls/view).

## Technical Foundation

At the core of Optimization Detective is the “URL Metric”, information about a page according to how it was loaded by a client with a specific viewport width. This includes which elements were visible in the initial viewport and which one was the LCP element. The URL Metric data is also extensible. Each URL on a site can have an associated set of these URL Metrics (stored in a custom post type) which are gathered from the visits of real users. It gathers samples of URL Metrics which are grouped according to WordPress's default responsive breakpoints:

1. Mobile: 0-480px
2. Phablet: 481-600px
3. Tablet: 601-782px
4. Desktop: \>782px

When no more URL Metrics are needed for a URL due to the sample size being obtained for the viewport group, it discontinues serving the JavaScript to gather the metrics (leveraging the [web-vitals.js](https://github.com/GoogleChrome/web-vitals) library). With the URL Metrics in hand, the output-buffered page is sent through the HTML Tag Processor and—when the [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) dependent plugin is installed—the images which were the LCP element for various breakpoints will get prioritized with high-priority preload links (along with `fetchpriority=high` on the actual `img` tag when it is the common LCP element across all breakpoints). LCP elements with background images added via inline `background-image` styles are also prioritized with preload links.

URL Metrics have a “freshness TTL” after which they will be stale and the JavaScript will be served again to start gathering metrics again to ensure that the right elements continue to get their loading prioritized. When a URL Metrics custom post type hasn't been touched in a while, it is automatically garbage-collected.

👉 **Note:** This plugin optimizes pages for actual visitors, and it depends on visitors to optimize pages (since URL Metrics need to be collected). As such, you won't see optimizations applied immediately after activating the plugin (and dependent plugin(s)). And since administrator users are not normal visitors typically, optimizations are not applied for admins by default (but this can be overridden with the `od_can_optimize_response` filter below). URL Metrics are not collected for administrators because it is likely that additional elements will be present on the page which are not also shown to non-administrators, meaning the URL Metrics could not reliably be reused between them.

When the `WP_DEBUG` constant is enabled, additional logging for Optimization Detective is added to the browser console.

## Extensions, Use Cases, and Examples

See [extensions documentation](./docs/extensions.md). 

## Hooks

See [hooks documentation](./docs/hooks.md).

## Installation

### Installation from within WordPress

1. Visit **Plugins > Add New** in the WordPress Admin.
2. Search for **Optimization Detective**.
3. Install and activate the **Optimization Detective** plugin.

### Manual installation

1. Download the plugin [ZIP from WordPress.org](https://downloads.wordpress.org/plugin/optimization-detective.zip) or, after following the [Getting Started instructions](https://make.wordpress.org/performance/handbook/performance-lab/), create a ZIP build from this repo via `npm run build:plugin:optimization-detective --env zip=true`.
2. Visit **Plugins > Add New Plugin** in the WordPress Admin.
3. Click **Upload Plugin**
4. Select the `optimization-detective.zip` file on your system from step 1 and click **Install Now**.
5. Click the **Active Plugin** button.

## Feedback

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). 

## Support

If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/optimization-detective/#new-topic-0).

## Contributing

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

The [plugin source code](https://github.com/WordPress/performance/tree/trunk/plugins/optimization-detective) is located in the [WordPress/performance](https://github.com/WordPress/performance) repo on GitHub.

## Security

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

## Changelog

Please see the [WordPress.org directory listing](https://wordpress.org/plugins/optimization-detective/) for the [changelog](https://wordpress.org/plugins/optimization-detective/#developers).
