<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ config('app.name') }} API Documentation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Stoplight Elements -->
  <script type="module" src="https://unpkg.com/@stoplight/elements/web-components.min.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements/styles.min.css" />

  <style>
    html, body {
      margin: 0;
      padding: 0;
      height: 100%;
      font-family: sans-serif;
    }

    #layout {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    elements-api {
      flex: 1;
      height: 100%;
    }

    elements-api::part(sidebar) {
      position: fixed;
      width: 300px; /* Adjust as needed */
      height: 100vh;
      overflow-y: auto;
      border-right: 1px solid #e0e0e0;
      background: #fff;
      z-index: 10;
    }

    elements-api::part(content) {
      margin-left: 300px; /* Match sidebar width */
      height: 100vh;
      overflow-y: auto;
      padding: 1rem;
    }

     /* Add this to hide the navbrand */
    elements-api::part(navbar-logo) {
      display: none;
    }

    /* hide nav brand */
    #mosaic-provider-react-aria-0-1 a[href^="https://stoplight.io"] {
      display: none;
    }
  </style>
</head>
<body>
  <div id="layout">
    <elements-api
      apiDescriptionUrl="/swagger.json"
      router="hash"
      layout="responsive"
      tryIt="true"
      search="true"
    ></elements-api>
  </div>
</body>
</html>
