<!DOCTYPE html>
<html lang="{$ContentLocale}">
    <head>
        <!-- Base Tag Content -->
        <% base_tag %>
        <!-- Meta Data -->
        $MetaTags(true)
        <link ref="icon" href="/favicon.ico" type="image/x-icon" />

        <%--
            Page Specific: Structured Data (in JSON-LD format) to add aditional
            information in SERP's and Google Knowledge Graph etc
        --%>
        $SiteConfig.getStructuredData()

        <%--
            Page Specific: Structured Data (in JSON-LD format) to add aditional
            information in SERP's and Google Knowledge Graph etc
        --%>
        $getStructuredData()
        
    </head>
    <body>
        <%-- Your Content Goes Here --%>
    </body>
</html>
