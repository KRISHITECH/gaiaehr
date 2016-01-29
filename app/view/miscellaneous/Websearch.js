/**
 * GaiaEHR (Electronic Health Records)
 * Copyright (C) 2013 Certun, LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

Ext.define('App.view.miscellaneous.Websearch',
{
	extend : 'App.ux.RenderPanel',
	id : 'panelWebsearch',
	pageTitle : _('national_library'),
	pageLayout : 'border',
	uses : ['Ext.grid.Panel'],
	initComponent : function()
	{

		var page = this;
		var search_type;
        var term;
		var rec;
		if (!Ext.ModelManager.isRegistered('webSearch'))
		{
            page.store = Ext.create('App.store.miscellaneous.webSearch');
            page.codingStore = Ext.create('Ext.data.Store',
            {
                fields: ['search', 'name'],
                data :
                    [
                        {"search":"code", "name":"Code"},
                        {"search":"term", "name":"Term"}
                    ]
            });
		}

		page.searchPanel = Ext.create('Ext.panel.Panel',
		{
			region : 'north',
			bodyPadding : '8 11 5 11',
			margin : '0 0 2 0',
			layout : 'anchor',
			items : [
			{
				xtype : 'radiogroup',
				fieldLabel : _('search_by'),
				items : [
                    {
                        boxLabel : _('heath_topics'),
                        name : 'type',
                        inputValue : 'health_topics'
                    },
                    {
                        boxLabel : 'SNOMED CT',
                        name : 'type',
                        inputValue : 'snomed'
                    },
                    {
                        boxLabel : 'RxCUI',
                        name : 'type',
                        inputValue : 'rxcui'
                    },
                    {
                        boxLabel : 'LOINC',
                        name : 'type',
                        inputValue : 'loinc'
                    },
                    {
                        boxLabel : 'NDC',
                        name : 'type',
                        inputValue : 'ndc'
                    },
                    {
                        boxLabel : 'ICD-9-CM',
                        name : 'type',
                        inputValue : 'icd9cm'
                    }
				],
				listeners :
				{
					change : function()
					{
						var value = this.getValue();
						search_type = value.type;
						page.searchField.enable();
						page.searchField.reset();
					}
				}
			},
            page.termField = Ext.create('Ext.form.ComboBox',
            {
                name: 'term',
                fieldLabel: _('code_term') + ':',
                store: page.codingStore,
                anchor : '100%',
                queryMode: 'local',
                displayField: 'name',
                valueField: 'search',
                editable: false,
                listeners:
                {
                    change: function()
                    {
                        term = this.getValue();
                    }
                }
            }),
            page.searchField = Ext.create('Ext.form.field.Text',
			{
				emptyText : _('web_search') + '...',
				enableKeyEvents : true,
				hideLabel : true,
				anchor : '100%',
				disabled : true,
				listeners :
				{
					keyup : function()
					{
						var query = this.getValue();
						if (query.length > 2)
						{
							page.store.load(
							{
								params :
								{
									type: search_type,
									q: query,
                                    term: term
								}
							});
						}
					},
					buffer : 500,
					focus : function()
					{
						page.viewPanel.collapse();
					}
				}
			}
            )]
		});
		page.searchRow = function(value, p, record)
		{
			return Ext.String.format('<div class="topic"><span class="search_title">{0}</span><br><span class="search_source">{1}</span><br><span class="search_snippet" style="white-space: normal;">{2}</span></div>', value, record.get('source') || "Unknown", record.get('snippet') || "Unknown");
		};
		page.onotesGrid = Ext.create('Ext.grid.Panel',
		{
			margin : '0 0 2 0',
			region : 'center',
			store : page.store,
			viewConfig :
			{
				deferEmptyText : false,
				emptyText : '<p class="search_nothing_found" style="padding: 10px 0 0 20px; font-size: 24px">' + _('nothing_found') + '!</p>',
				stripeRows : true,
				loadingText : _('searching') + '... ' + _('please_wait')
			},
			columns : [
			{
				flex : 1,
				header : _('search_results'),
				sortable : true,
				dataIndex : 'title',
				renderer : page.searchRow
			},
			{
				hidden : true,
				sortable : true,
				dataIndex : 'source'
			},
			{
				hidden : true,
				sortable : true,
				dataIndex : 'snippet'
			}],
			tbar : Ext.create('Ext.PagingToolbar',
			{
				store : page.store,
				displayInfo : true,
				emptyMsg : _('nothing_to_display'),
				plugins : Ext.create('Ext.ux.SlidingPager',
				{
				})
			}),
			listeners :
			{
				itemclick : function(DataView, record, item, rowIndex)
				{
					rec = page.store.getAt(rowIndex);
					page.viewPanel.update(rec.data);
                    page.viewPanel.expand();
				}
			}
		});
		// END GRID
		page.viewPanel = Ext.create('Ext.panel.Panel',
		{
			region : 'south',
			height : 300,
			collapsible : true,
			collapsed : true,
			layout : 'fit',
			frame : true,
			bodyBorder : true,
			tpl : Ext.create('Ext.XTemplate', '<div class="search_container">', '<div class="search_data">', '<h3 class="search_title">' + _('title') + ': {title}</h3>', '<h4 class="search_source">' + _('source') + ': {source}</h4>', '</div>', '<div class="search_body">{FullSummary}</div>', '</div>')
		});

		page.pageBody = [page.searchPanel, page.onotesGrid, page.viewPanel];
		page.callParent(arguments);
        page.termField.select('code');
	}, // end of initComponent
	/**
	 * This function is called from Viewport.js when
	 * this panel is selected in the navigation panel.
	 * place inside this function all the functions you want
	 * to call every this panel becomes active
	 */
	onActive : function(callback)
	{
		callback(true);
	}
});
//ens UserPage class
