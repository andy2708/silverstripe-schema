<h1>$Title</h1>

<div id="wrapper">
    <% if $TeamMember %>
        <% with $TeamMember %>
            <%--
                Member Specific: Structured Data relating to this staff member
            --%>
            $getStructuredData()
            <div>
                <h2>$getName</h2>
                <p>$SubTitle</p>
            </div>
        <% end_with %>
    <% end_if %>
</div>
