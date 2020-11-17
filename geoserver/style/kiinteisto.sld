<StyledLayerDescriptor xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0.0" xsi:schemaLocation="http://www.opengis.net/sld StyledLayerDescriptor.xsd">
  <NamedLayer>
    <Name>Simple point with stroke</Name>
    <UserStyle>
      <Title>GeoServer SLD Cook Book: Simple point with stroke</Title>
      <FeatureTypeStyle>
        <Rule>
          <Name>Valtakunnallinen</Name>
          <Title>Valtakunnallinen</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
              <ogc:Literal>Valtakunnallinen</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>              
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#8f6bb1</CssParameter>     
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#0000FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Seudullinen</Name>
          <Title>Seudullinen</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
              <ogc:Literal>Seudullinen</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>              
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#DF0101</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#0000FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Paikallinen</Name>
          <Title>Paikallinen</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
              <ogc:Literal>Paikallinen</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>              
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#0040FF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#0000FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Maisemallinen</Name>
          <Title>Maisemallinen</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
              <ogc:Literal>Maisemallinen</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>              
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#12994a</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#0000FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Historiallinen</Name>
          <Title>Historiallinen</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
              <ogc:Literal>Historiallinen</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>              
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#b75819</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#0000FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
        <Rule>
          <Name>Muut inventoidut</Name>
          <Title>Muut inventoidut</Title>
          <ogc:Filter>
            <ogc:PropertyIsNull>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
            </ogc:PropertyIsNull>
          </ogc:Filter>              
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#bdbdbd</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#0000FF</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>12</Size>
            </Graphic>
          </PointSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
