window.doc_page = {
    "addon": "Low Seg2Cat",
    "title": "Template Variables",
    "desc": "<p>Low Seg2Cat looks for categories whose URL titles match URI segments and registers the variables below. These are single variables: there is no Low Seg2Cat tag pair to open or close. Review <a href=\"settings\">Settings</a> to control which sites and category groups are searched.</p><p>Numbered examples use `/articles/news`, where `news` is a category URL title in segment 2. Replace the segment number and template paths for your site. If a segment has no matching category, its registered category variables are empty strings. A nonmatching URI pattern skips registration entirely; see <a href=\"settings#uri-pattern\">URI pattern</a> and <a href=\"settings#set-all-segments\">Set all segments</a>.</p>",
    "toc_intro": "Choose variables for one segment, the final segment, or all matching categories:",
    "terms": [],
    "sections": [
        {
            "type": "tagtoc"
        },
        {
            "type": "tags"
        }
    ],
    "tags": [
        {
            "tag": "Numbered segment variables",
            "shortname": "numbered_segments",
            "anchor": "numbered-segments",
            "desc": "Replace n with the URI segment number, such as 2 for the news segment in /articles/news.",
            "sections": [
                {
                    "type": "variables",
                    "title": "Category information",
                    "desc": "Use the category ID as a guard when markup should appear only for a matching category. A top-level category can have parent ID `0`, so do not use the parent ID to decide whether a category matched.",
                    "items": [
                        {
                            "item": "if segment_2_category_id",
                            "anchor": "matching-category",
                            "type": "conditional example",
                            "desc": "Check the category ID before displaying category-specific markup.",
                            "examples": [
                                {
                                    "tag_example": "{if segment_2_category_id}\n    <h1>{segment_2_category_name}</h1>\n    <p>{segment_2_category_description}</p>\n{/if}"
                                }
                            ]
                        },
                        {
                            "item": "segment_n_category_id",
                            "type": "variable",
                            "desc": "The matching category ID.",
                            "examples": [
                                {
                                    "tag_example": "<p>Category ID: {segment_2_category_id}</p>"
                                }
                            ]
                        },
                        {
                            "item": "segment_n_category_name",
                            "type": "variable",
                            "desc": "The matching category name.",
                            "examples": [
                                {
                                    "tag_example": "<h1>{segment_2_category_name}</h1>"
                                }
                            ]
                        },
                        {
                            "item": "segment_n_category_description",
                            "type": "variable",
                            "desc": "The matching category description.",
                            "examples": [
                                {
                                    "tag_example": "<p>{segment_2_category_description}</p>"
                                }
                            ]
                        },
                        {
                            "item": "segment_n_category_image",
                            "type": "variable",
                            "desc": "The Category Image value. Enable <a href=\"settings#parse-file-paths\">Parse file paths</a> when stored file references need to become usable paths.",
                            "examples": [
                                {
                                    "tag_example": "{if segment_2_category_image}\n    <img src=\"{segment_2_category_image}\" alt=\"\">\n{/if}"
                                }
                            ]
                        },
                        {
                            "item": "segment_n_category_parent_id",
                            "type": "variable",
                            "desc": "The matching category's parent ID. A top-level category has parent ID `0`; it still has its own category ID.",
                            "examples": [
                                {
                                    "tag_example": "<p>Parent category ID: {segment_2_category_parent_id}</p>"
                                }
                            ]
                        },
                        {
                            "item": "segment_n_category_group_id",
                            "type": "variable",
                            "desc": "The ID of the group containing the matching category.",
                            "examples": [
                                {
                                    "tag_example": "<p>Category group ID: {segment_2_category_group_id}</p>"
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        {
            "tag": "Last segment variables",
            "shortname": "last_segment",
            "anchor": "last-segment",
            "desc": "Use the category matching the final URI segment. This is the final segment, rather than the last segment anywhere in the URI that matched a category.",
            "sections": [
                {
                    "type": "variables",
                    "title": "Final segment category",
                    "desc": "For `/articles/news/P20`, these values are empty unless that final segment matches a category. With <a href=\"settings#ignore-pagination\">Ignore pagination</a> set to Yes, the `/P20` suffix is removed before the lookup, so the variables can describe `news`.",
                    "items": [
                        {
                            "item": "last_segment_category_id",
                            "type": "variable",
                            "desc": "The final segment's matching category ID.",
                            "examples": [
                                {
                                    "tag_example": "<p>Category ID: {last_segment_category_id}</p>"
                                }
                            ]
                        },
                        {
                            "item": "last_segment_category_name",
                            "type": "variable",
                            "desc": "The final segment's matching category name.",
                            "examples": [
                                {
                                    "tag_example": "<h1>{last_segment_category_name}</h1>"
                                }
                            ]
                        },
                        {
                            "item": "last_segment_category_description",
                            "type": "variable",
                            "desc": "The final segment's matching category description.",
                            "examples": [
                                {
                                    "tag_example": "<p>{last_segment_category_description}</p>"
                                }
                            ]
                        },
                        {
                            "item": "last_segment_category_image",
                            "type": "variable",
                            "desc": "The Category Image value. Enable <a href=\"settings#parse-file-paths\">Parse file paths</a> when stored file references need to become usable paths.",
                            "examples": [
                                {
                                    "tag_example": "{if last_segment_category_image}\n    <img src=\"{last_segment_category_image}\" alt=\"\">\n{/if}"
                                }
                            ]
                        },
                        {
                            "item": "last_segment_category_parent_id",
                            "type": "variable",
                            "desc": "The final segment's matching category's parent ID. A top-level category has parent ID `0`; it still has its own category ID.",
                            "examples": [
                                {
                                    "tag_example": "<p>Parent category ID: {last_segment_category_parent_id}</p>"
                                }
                            ]
                        },
                        {
                            "item": "last_segment_category_group_id",
                            "type": "variable",
                            "desc": "The ID of the group containing the matching category.",
                            "examples": [
                                {
                                    "tag_example": "<p>Category group ID: {last_segment_category_group_id}</p>"
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        {
            "tag": "Combined category IDs",
            "shortname": "category_ids",
            "anchor": "combined-category-ids",
            "desc": "Return the IDs of categories matched across URI segments, in URI order. Both values are empty when no category matches.",
            "sections": [
                {
                    "type": "variables",
                    "title": "ID lists",
                    "desc": "The examples pass the existing ID lists into a Channel Entries category filter. Keep the conditional guard: an empty category value would not restrict the entries to matched categories. Replace `articles` with your channel's short name.",
                    "items": [
                        {
                            "item": "segment_category_ids",
                            "type": "variable",
                            "desc": "Join matched category IDs with `&amp;`, for example `4&amp;12`. In a Channel Entries `category` parameter, this requires entries assigned to all listed categories.",
                            "examples": [
                                {
                                    "tag_example": "{if segment_category_ids}\n    {exp:channel:entries channel=\"articles\" category=\"{segment_category_ids}\" dynamic=\"no\"}\n        <h2>{title}</h2>\n    {/exp:channel:entries}\n{/if}"
                                }
                            ]
                        },
                        {
                            "item": "segment_category_ids_piped",
                            "type": "variable",
                            "desc": "Join matched category IDs with `|`, for example `4|12`. In a Channel Entries `category` parameter, this matches entries assigned to any listed category; it is not an exclusive-or operation.",
                            "examples": [
                                {
                                    "tag_example": "{if segment_category_ids_piped}\n    {exp:channel:entries channel=\"articles\" category=\"{segment_category_ids_piped}\" dynamic=\"no\"}\n        <h2>{title}</h2>\n    {/exp:channel:entries}\n{/if}"
                                }
                            ]
                        }
                    ]
                }
            ]
        }
    ]
};
