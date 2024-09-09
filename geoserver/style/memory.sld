<StyledLayerDescriptor version="1.0.0"
  xsi:schemaLocation="http://www.opengis.net/sld StyledLayerDescriptor.xsd"
  xmlns="http://www.opengis.net/sld"
  xmlns:ogc="http://www.opengis.net/ogc"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>Point Layer</Name>
    <UserStyle>
      <Title>Triangle Point Style with Border</Title>
      <Abstract>A triangle marker with a thin black border</Abstract>
      <FeatureTypeStyle>
        <Rule>
          <PointSymbolizer>
            <Graphic>
              <Mark>
                <WellKnownName>triangle</WellKnownName> <!-- Defines the triangle shape -->
                <Fill>
                  <CssParameter name="fill">
                    <ogc:PropertyName>aiheen_vari</ogc:PropertyName> <!-- Sets fill color dynamically based on 'color' property -->
                  </CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#000000</CssParameter> <!-- Black border color -->
                  <CssParameter name="stroke-width">1</CssParameter> <!-- Thin border width -->
                </Stroke>
              </Mark>
              <Size>12</Size> <!-- Size of the triangle marker -->
              <Rotation>180</Rotation>
            </Graphic>
          </PointSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>prikka_id</ogc:PropertyName>
            </Label>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">8</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>             
            </Font>
            <LabelPlacement>
              <PointPlacement>
                <AnchorPoint>
                  <AnchorPointX>0.5</AnchorPointX>
                  <AnchorPointY>-1.5</AnchorPointY>
                </AnchorPoint>
              </PointPlacement>
            </LabelPlacement>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter> <!-- Text color -->
            </Fill>
            <Halo>
              <Radius>1</Radius> <!-- Width of the halo (background) around the text -->
              <Fill>
                <CssParameter name="fill">#FFFFFF</CssParameter> <!-- White background color -->
              </Fill>
            </Halo>
          </TextSymbolizer>        
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
