(function(){
    try {
        var k = 'segob_theme', d = 'segob_dark_variant';
        if (localStorage.getItem(k) !== 'dark') return;
        var v = localStorage.getItem(d) || 'deep';
        if (v !== 'soft' && v !== 'slate') v = 'deep';
        document.documentElement.classList.add('theme-dark', 'theme-dark--' + v);
    } catch (e) {}
})();
