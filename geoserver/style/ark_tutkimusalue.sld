<StyledLayerDescriptor version="1.0.0"
                       xsi:schemaLocation="http://www.opengis.net/sld StyledLayerDescriptor.xsd"
                       xmlns="http://www.opengis.net/sld"
                       xmlns:ogc="http://www.opengis.net/ogc"
                       xmlns:xlink="http://www.w3.org/1999/xlink"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>ark_tutkimusalue</Name>
    <UserStyle>
      <Title>piste</Title>
      <FeatureTypeStyle>
        <Rule>
          <Name>Kaivaus</Name>
          <Title>Kaivaus</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Kaivaus</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti_piste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>square</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#28561F</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FFD250</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Kaivaus</Name>
          <Title>Kaivaus</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Kaivaus</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Fill>
             <GraphicFill>
              <Graphic>
                <Mark>
                  <WellKnownName>shape://backslash</WellKnownName>
                  <Stroke>
                    <CssParameter name="stroke">#28561F</CssParameter>
                    <CssParameter name="stroke-width">1</CssParameter>
                  </Stroke>
                </Mark>
              </Graphic>
             </GraphicFill>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#FFD250</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
        </Rule>

        <Rule>
          <Name>Koekaivaus</Name>
          <Title>Koekaivaus</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Kaivaus</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti_piste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>square</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#646000</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FFD250</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Koekaivaus</Name>
          <Title>Koekaivaus</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Koekaivaus</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Fill>
             <GraphicFill>
              <Graphic>
                <Mark>
                  <WellKnownName>shape://slash</WellKnownName>
                  <Stroke>
                    <CssParameter name="stroke">#646000</CssParameter>
                    <CssParameter name="stroke-width">1</CssParameter>
                  </Stroke>
                </Mark>
              </Graphic>
             </GraphicFill>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#FFD250</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
        </Rule>

        <Rule>
          <Name>Konekaivuun valvonta</Name>
          <Title>Konekaivuun valvonta</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Konekaivuun valvonta</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti_piste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>square</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#000000</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FFD250</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Konekaivuun valvonta</Name>
          <Title>Konekaivuun valvonta</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Konekaivuun valvonta</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Fill>
             <GraphicFill>
              <Graphic>
                <Mark>
                  <WellKnownName>shape://backslash</WellKnownName>
                  <Stroke>
                    <CssParameter name="stroke">#000000</CssParameter>
                    <CssParameter name="stroke-width">1</CssParameter>
                  </Stroke>
                </Mark>
              </Graphic>
             </GraphicFill>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#FFD250</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
        </Rule>

        <Rule>
          <Name>Inventointi</Name>
          <Title>Inventointi</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Inventointi</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti_piste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>square</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#171757</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FFD250</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Inventointi</Name>
          <Title>Inventointi</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Inventointi</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Fill>
             <GraphicFill>
              <Graphic>
                <Mark>
                  <WellKnownName>shape://backslash</WellKnownName>
                  <Stroke>
                    <CssParameter name="stroke">#171757</CssParameter>
                    <CssParameter name="stroke-width">1</CssParameter>
                  </Stroke>
                </Mark>
              </Graphic>
             </GraphicFill>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#FFD250</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
        </Rule>

        <Rule>
          <Name>Tarkastus</Name>
          <Title>Tarkatus</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Tarkastus</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti_piste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>square</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#640505</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#FFD250</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Tarkastus</Name>
          <Title>Tarkastus</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Tarkastus</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Fill>
             <GraphicFill>
              <Graphic>
                <Mark>
                  <WellKnownName>shape://backslash</WellKnownName>
                  <Stroke>
                    <CssParameter name="stroke">#640505</CssParameter>
                    <CssParameter name="stroke-width">1</CssParameter>
                  </Stroke>
                </Mark>
              </Graphic>
             </GraphicFill>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#FFD250</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
        </Rule>

        <Rule>
          <Name>Irtoloyto</Name>
          <Title>Irtoloyto</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Irtolöytö</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti_piste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>square</WellKnownName>
                <Fill>
                  <CssParameter name="fill"></CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#603E10</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Irtoloyto</Name>
          <Title>Irtoloyto</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>tutkimuslaji</ogc:PropertyName>
              <ogc:Literal>Irtolöytö</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>sijainti</ogc:PropertyName>
            </Geometry>
            <Fill>
             <GraphicFill>
              <Graphic>
                <Mark>
                  <WellKnownName>shape://backslash</WellKnownName>
                  <Stroke>
                    <CssParameter name="stroke">#603E10</CssParameter>
                    <CssParameter name="stroke-width">1</CssParameter>
                  </Stroke>
                </Mark>
              </Graphic>
             </GraphicFill>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#FFD250</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
