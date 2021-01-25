<StyledLayerDescriptor version="1.0.0"
                       xsi:schemaLocation="http://www.opengis.net/sld StyledLayerDescriptor.xsd"
                       xmlns="http://www.opengis.net/sld"
                       xmlns:ogc="http://www.opengis.net/ogc"
                       xmlns:xlink="http://www.w3.org/1999/xlink"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>kohde</Name>
    <UserStyle>
      <Title>piste</Title>
      <FeatureTypeStyle>		
		<Rule>
          <Name>Ei määritelty</Name>
          <Title>Ei määritelty</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Ei määritelty</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC5699</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Ei määritelty</Name>
          <Title>Ei määritelty</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Ei määritelty</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Ei määritelty</Name>
          <Title>Ei määritelty</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Ei määritelty</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC56C8</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Ei määritelty</Name>
          <Title>Ei määritelty</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Ei määritelty</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#FFFFFF</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC5699</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Ei määritelty</Name>
          <Title>Ei määritelty</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Ei määritelty</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Ei määritelty</Name>
          <Title>Ei määritelty</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Ei määritelty</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#FFFFFF</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC56C8</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
				
		<Rule>
          <Name>Kiinteä muinaisjäännös</Name>
          <Title>Kiinteä muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Kiinteä muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#E60000</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Kiinteä muinaisjäännös</Name>
          <Title>Kiinteä muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Kiinteä muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Kiinteä muinaisjäännös</Name>
          <Title>Kiinteä muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Kiinteä muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#E60000</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Kiinteä muinaisjäännös</Name>
          <Title>Kiinteä muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Kiinteä muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#E40000</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#E40000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Kiinteä muinaisjäännös</Name>
          <Title>Kiinteä muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Kiinteä muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Kiinteä muinaisjäännös</Name>
          <Title>Kiinteä muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Kiinteä muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#E40000</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Luonnonmuodostuma</Name>
          <Title>Luonnonmuodostuma</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Luonnonmuodostuma</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#01C6FF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Luonnonmuodostuma</Name>
          <Title>Luonnonmuodostuma</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Luonnonmuodostuma</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Luonnonmuodostuma</Name>
          <Title>Luonnonmuodostuma</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Luonnonmuodostuma</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#01C6FF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Luonnonmuodostuma</Name>
          <Title>Luonnonmuodostuma</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Luonnonmuodostuma</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Luonnonmuodostuma</Name>
          <Title>Luonnonmuodostuma</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Luonnonmuodostuma</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Luonnonmuodostuma</Name>
          <Title>Luonnonmuodostuma</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Luonnonmuodostuma</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		
		<Rule>
          <Name>Löytöpaikka</Name>
          <Title>Löytöpaikka</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Löytöpaikka</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FF7F01</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Löytöpaikka</Name>
          <Title>Löytöpaikka</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Löytöpaikka</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Löytöpaikka</Name>
          <Title>Löytöpaikka</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Löytöpaikka</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FF7F01</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Löytöpaikka</Name>
          <Title>Löytöpaikka</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Löytöpaikka</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Löytöpaikka</Name>
          <Title>Löytöpaikka</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Löytöpaikka</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Löytöpaikka</Name>
          <Title>Löytöpaikka</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Löytöpaikka</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		
		<Rule>
          <Name>Mahdollinen muinaisjäännös</Name>
          <Title>Mahdollinen muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Mahdollinen muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FF00FF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Mahdollinen muinaisjäännös</Name>
          <Title>Mahdollinen muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Mahdollinen muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Mahdollinen muinaisjäännös</Name>
          <Title>Mahdollinen muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Mahdollinen muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FF00FF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Mahdollinen muinaisjäännös</Name>
          <Title>Mahdollinen muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Mahdollinen muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#FF00FF</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF00FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Mahdollinen muinaisjäännös</Name>
          <Title>Mahdollinen muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Mahdollinen muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Mahdollinen muinaisjäännös</Name>
          <Title>Mahdollinen muinaisjäännös</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Mahdollinen muinaisjäännös</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#FF00FF</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF00FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		
		<Rule>
          <Name>Muu kohde</Name>
          <Title>Muu kohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kohde</Name>
          <Title>Muu kohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kohde</Name>
          <Title>Muu kohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Muu kohde</Name>
          <Title>Muu kohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kohde</Name>
          <Title>Muu kohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kohde</Name>
          <Title>Muu kohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
				
		<Rule>
          <Name>Muu kulttuuriperintökohde</Name>
          <Title>Muu kulttuuriperintökohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kulttuuriperintökohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#B67F4A</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kulttuuriperintökohde</Name>
          <Title>Muu kulttuuriperintökohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kulttuuriperintökohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kulttuuriperintökohde</Name>
          <Title>Muu kulttuuriperintökohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kulttuuriperintökohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#B67F4A</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Muu kulttuuriperintökohde</Name>
          <Title>Muu kulttuuriperintökohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kulttuuriperintökohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#B67F4A</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#B67F4A</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kulttuuriperintökohde</Name>
          <Title>Muu kulttuuriperintökohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kulttuuriperintökohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Muu kulttuuriperintökohde</Name>
          <Title>Muu kulttuuriperintökohde</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Muu kulttuuriperintökohde</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#B67F4A</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#B67F4A</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		
		<Rule>
          <Name>Poistettava</Name>
          <Title>Poistettava</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettava</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC5699</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettava</Name>
          <Title>Poistettava</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettava</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettava</Name>
          <Title>Poistettava</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettava</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC56C8</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Poistettava</Name>
          <Title>Poistettava</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettava</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
                  <CssParameter name="fill">#FFFFFF</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC5699</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettava</Name>
          <Title>Poistettava</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettava</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettava</Name>
          <Title>Poistettava</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettava</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#FFFFFF</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FC5699</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		
		<Rule>
          <Name>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Name>
          <Title>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POINT</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#828282</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Name>
          <Title>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#A9A9A9</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Name>
          <Title>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POINT</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>star</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#828282</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>		
		
		<Rule>
          <Name>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Name>
          <Title>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			 <ogc:PropertyIsEqualTo>
				<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
				<ogc:Literal>POLYGON</ogc:Literal>
			  </ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>            
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Name>
          <Title>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#A9A9A9</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FF0000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>         
          </PolygonSymbolizer>
        </Rule>
		<Rule>
          <Name>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Name>
          <Title>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</Title>
          <ogc:Filter>
			<ogc:And>
				<ogc:PropertyIsEqualTo>
				  <ogc:PropertyName>kohdelaji</ogc:PropertyName>
				  <ogc:Literal>Poistettu kiinteä muinaisjäännös (ei rauhoitettu)</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>sijainti_tyyppi</ogc:PropertyName>
					<ogc:Literal>POLYGON</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>tuhoutunut</ogc:PropertyName>
					<ogc:Literal>false</ogc:Literal>
				</ogc:PropertyIsEqualTo>
				<ogc:PropertyIsEqualTo>
					<ogc:PropertyName>on_alakohde</ogc:PropertyName>
					<ogc:Literal>true</ogc:Literal>
				</ogc:PropertyIsEqualTo>
			</ogc:And>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
                <Fill>
					<CssParameter name="fill">#828282</CssParameter>
					<CssParameter name="fill-opacity">0.5</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>              
          </PolygonSymbolizer>
        </Rule>
		
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
