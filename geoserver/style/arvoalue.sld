<StyledLayerDescriptor version="1.0.0" 
                       xsi:schemaLocation="http://www.opengis.net/sld StyledLayerDescriptor.xsd" 
                       xmlns="http://www.opengis.net/sld" 
                       xmlns:ogc="http://www.opengis.net/ogc" 
                       xmlns:xlink="http://www.w3.org/1999/xlink" 
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>arvoalue</Name>
    <UserStyle>
      <Title>piste</Title>
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
            <Geometry>
              <ogc:PropertyName>keskipiste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#8f6bb1</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#16bc59</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <LabelPlacement>
              <PointPlacement>
                <AnchorPoint>
                  <AnchorPointX>0.5</AnchorPointX>
                  <AnchorPointY>0.75</AnchorPointY>
                </AnchorPoint>
              </PointPlacement>
            </LabelPlacement>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
        </Rule>
        <Rule>
          <Name>Valtakunnallinen</Name>
          <Title>Valtakunnallinen</Title>
          <ogc:Filter>
            <ogc:PropertyIsEqualTo>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
              <ogc:Literal>Valtakunnallinen</ogc:Literal>
            </ogc:PropertyIsEqualTo>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>aluerajaus</ogc:PropertyName>
            </Geometry>
            <Fill>
              <CssParameter name="fill">#8f6bb1</CssParameter>
              <CssParameter name="fill-opacity">0.5</CssParameter>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#16bc59</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Geometry>
              <ogc:Function name="centroid">
                <ogc:PropertyName>aluerajaus</ogc:PropertyName>
              </ogc:Function>
            </Geometry>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
            <Geometry>
              <ogc:PropertyName>keskipiste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#DF0101</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#16bc59</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <LabelPlacement>
              <PointPlacement>
                <AnchorPoint>
                  <AnchorPointX>0.5</AnchorPointX>
                  <AnchorPointY>0.75</AnchorPointY>
                </AnchorPoint>
              </PointPlacement>
            </LabelPlacement>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>aluerajaus</ogc:PropertyName>
            </Geometry>
            <Fill>
              <CssParameter name="fill">#DF0101</CssParameter>
              <CssParameter name="fill-opacity">0.5</CssParameter>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#16bc59</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Geometry>
              <ogc:Function name="centroid">
                <ogc:PropertyName>aluerajaus</ogc:PropertyName>
              </ogc:Function>
            </Geometry>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
            <Geometry>
              <ogc:PropertyName>keskipiste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#0040FF</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#16bc59</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <LabelPlacement>
              <PointPlacement>
                <AnchorPoint>
                  <AnchorPointX>0.5</AnchorPointX>
                  <AnchorPointY>0.75</AnchorPointY>
                </AnchorPoint>
              </PointPlacement>
            </LabelPlacement>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>aluerajaus</ogc:PropertyName>
            </Geometry>
            <Fill>
              <CssParameter name="fill">#0040FF</CssParameter>
              <CssParameter name="fill-opacity">0.5</CssParameter>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#16bc59</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Geometry>
              <ogc:Function name="centroid">
                <ogc:PropertyName>aluerajaus</ogc:PropertyName>
              </ogc:Function>
            </Geometry>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
            <Geometry>
              <ogc:PropertyName>keskipiste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#12994a</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#16bc59</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <LabelPlacement>
              <PointPlacement>
                <AnchorPoint>
                  <AnchorPointX>0.5</AnchorPointX>
                  <AnchorPointY>0.75</AnchorPointY>
                </AnchorPoint>
              </PointPlacement>
            </LabelPlacement>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>aluerajaus</ogc:PropertyName>
            </Geometry>
            <Fill>
              <CssParameter name="fill">#12994a</CssParameter>
              <CssParameter name="fill-opacity">0.5</CssParameter>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#16bc59</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Geometry>
              <ogc:Function name="centroid">
                <ogc:PropertyName>aluerajaus</ogc:PropertyName>
              </ogc:Function>
            </Geometry>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
            <Geometry>
              <ogc:PropertyName>keskipiste</ogc:PropertyName>
            </Geometry>
            <Graphic>
              <Mark>
                <WellKnownName>circle</WellKnownName>
                <Fill>
                  <CssParameter name="fill">#b75819</CssParameter>
                </Fill>
                <Stroke>
                  <CssParameter name="stroke">#16bc59</CssParameter>
                  <CssParameter name="stroke-width">1</CssParameter>
                </Stroke>
              </Mark>
              <Size>14</Size>
            </Graphic>
          </PointSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <LabelPlacement>
              <PointPlacement>
                <AnchorPoint>
                  <AnchorPointX>0.5</AnchorPointX>
                  <AnchorPointY>0.75</AnchorPointY>
                </AnchorPoint>
              </PointPlacement>
            </LabelPlacement>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
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
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>aluerajaus</ogc:PropertyName>
            </Geometry>
            <Fill>
              <CssParameter name="fill">#b75819</CssParameter>
              <CssParameter name="fill-opacity">0.5</CssParameter>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#16bc59</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Geometry>
              <ogc:Function name="centroid">
                <ogc:PropertyName>aluerajaus</ogc:PropertyName>
              </ogc:Function>
            </Geometry>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
        </Rule><Rule>
        <Name>Muut inventoidut</Name>
        <Title>Muut inventoidut</Title>
        <ogc:Filter>
          <ogc:PropertyIsNull>
            <ogc:PropertyName>arvoluokka</ogc:PropertyName>
          </ogc:PropertyIsNull>
        </ogc:Filter> 
        <PointSymbolizer>
          <Geometry>
            <ogc:PropertyName>keskipiste</ogc:PropertyName>
          </Geometry>
          <Graphic>
            <Mark>
              <WellKnownName>circle</WellKnownName>
              <Fill>
                <CssParameter name="fill">#bdbdbd</CssParameter>
              </Fill>
              <Stroke>
                <CssParameter name="stroke">#16bc59</CssParameter>
                <CssParameter name="stroke-width">1</CssParameter>
              </Stroke>
            </Mark>
            <Size>14</Size>
          </Graphic>
        </PointSymbolizer>
        <TextSymbolizer>
          <Label>
            <ogc:PropertyName>inventointinumero</ogc:PropertyName>
          </Label>
          <Font>
            <CssParameter name="font-family">Arial</CssParameter>
            <CssParameter name="font-size">10</CssParameter>
            <CssParameter name="font-style">normal</CssParameter>
            <CssParameter name="font-weight">bold</CssParameter>
          </Font>
          <LabelPlacement>
            <PointPlacement>
              <AnchorPoint>
                <AnchorPointX>0.5</AnchorPointX>
                <AnchorPointY>0.75</AnchorPointY>
              </AnchorPoint>
            </PointPlacement>
          </LabelPlacement>
          <Fill>
            <CssParameter name="fill">#000000</CssParameter>
          </Fill>
        </TextSymbolizer>
        </Rule>
        <Rule>
          <Name>Muut inventoidut</Name>
          <Title>Muut inventoidut</Title>
          <ogc:Filter>
            <ogc:PropertyIsNull>
              <ogc:PropertyName>arvoluokka</ogc:PropertyName>
            </ogc:PropertyIsNull>
          </ogc:Filter>
          <PolygonSymbolizer>
            <Geometry>
              <ogc:PropertyName>aluerajaus</ogc:PropertyName>
            </Geometry>
            <Fill>
              <CssParameter name="fill">#bdbdbd</CssParameter>
              <CssParameter name="fill-opacity">0.5</CssParameter>
            </Fill>
            <Stroke>
              <CssParameter name="stroke">#16bc59</CssParameter>
              <CssParameter name="stroke-width">1</CssParameter>
            </Stroke>
          </PolygonSymbolizer>
          <TextSymbolizer>
            <Label>
              <ogc:PropertyName>inventointinumero</ogc:PropertyName>
            </Label>
            <Geometry>
              <ogc:Function name="centroid">
                <ogc:PropertyName>aluerajaus</ogc:PropertyName>
              </ogc:Function>
            </Geometry>
            <Font>
              <CssParameter name="font-family">Arial</CssParameter>
              <CssParameter name="font-size">10</CssParameter>
              <CssParameter name="font-style">normal</CssParameter>
              <CssParameter name="font-weight">bold</CssParameter>
            </Font>
            <Fill>
              <CssParameter name="fill">#000000</CssParameter>
            </Fill>
          </TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
