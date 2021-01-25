<StyledLayerDescriptor version="1.0.0" 
                       xsi:schemaLocation="http://www.opengis.net/sld StyledLayerDescriptor.xsd" 
                       xmlns="http://www.opengis.net/sld" 
                       xmlns:ogc="http://www.opengis.net/ogc" 
                       xmlns:xlink="http://www.w3.org/1999/xlink" 
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>kiinteisto</Name>
    <UserStyle>
      <Title>piste</Title>
      <FeatureTypeStyle>
        <Rule>
          <Name>piste</Name>
          <Title>piste</Title>                  
          <PointSymbolizer>
            <Geometry>
              <ogc:PropertyName>kiinteiston_sijainti</ogc:PropertyName>
            </Geometry>
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
