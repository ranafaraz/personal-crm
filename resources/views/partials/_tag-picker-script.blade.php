<script>
function tagPicker(allTags, selectedIds) {
    return {
        all: allTags,
        selected: allTags.filter(t => selectedIds.includes(t.id)),
        filtered: [],
        search: '',
        open: false,
        init() {
            this.filtered = this.all.filter(t => !this.selected.find(s => s.id === t.id));
        },
        filterTags() {
            const q = this.search.toLowerCase().trim();
            this.filtered = this.all.filter(t =>
                !this.selected.find(s => s.id === t.id) &&
                (q === '' || t.name.toLowerCase().includes(q))
            );
            this.open = true;
        },
        addTag(tag) {
            if (!this.selected.find(s => s.id === tag.id)) this.selected.push(tag);
            this.search = ''; this.filterTags(); this.$refs.tagInput.focus();
        },
        removeTag(tag) { this.selected = this.selected.filter(s => s.id !== tag.id); this.filterTags(); },
        backspaceTag() { if (this.search === '' && this.selected.length) this.selected.pop(); },
        exactMatch() { return this.all.some(t => t.name.toLowerCase() === this.search.toLowerCase().trim()); },
        confirmTag() { if (this.filtered.length) this.addTag(this.filtered[0]); else if (this.search.trim()) this.createTag(); },
        async createTag() {
            const name = this.search.trim();
            if (!name) return;
            const res = await fetch('{{ route("tags.store") }}', {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                body: JSON.stringify({ name })
            });
            if (res.ok) {
                const tag = await res.json();
                this.all.push(tag);
                this.addTag(tag);
            }
        },
    };
}
</script>
