<div
    @class ([
        'capell-article-meta article-meta mt-10 flex flex-col gap-5 md:flex-row md:items-center md:justify-between',
        'border-y border-slate-200 py-6' => $withAuthor && $author,
        'pt-2' => ! ($withAuthor && $author) && $tags->isNotEmpty(),
    ])
>
    @if ($withAuthor && $author)
        <x-capell-blog::page.author
            class="min-w-0"
            :author="$author"
            :profile-image="$profileImage"
        />
    @endif

    @if ($tags->isNotEmpty())
        <div class="article-tags flex flex-col gap-x-10 gap-y-4 md:items-end">
            <x-capell-blog::page.tags
                :$tagLinks
                with_tag_icon="true"
            />
        </div>
    @endif
</div>
