"use strict";

document.addEventListener("DOMContentLoaded", function () {
    // automatically submit the form so the SP will receive the SAMLResponse
    // on its ACS without user involvement...
    document.forms["submit"].submit();
});
