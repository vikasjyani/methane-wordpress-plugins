import unittest
import pandas as pd
import os
from kseb_energy_futures_platform.modules.pypsa_modeling.file_parser import parse_pypsa_input_template

class TestPyPSAInputParser(unittest.TestCase):
    SAMPLE_FILE_DIR = "sample_data" # Relative to tests directory
    SAMPLE_FILE_PATH = os.path.join(SAMPLE_FILE_DIR, "sample_pypsa_input.xlsx")

    @classmethod
    def setUpClass(cls):
        """Create a sample Excel file for testing PyPSA input template parsing."""
        if not os.path.exists(cls.SAMPLE_FILE_DIR):
            os.makedirs(cls.SAMPLE_FILE_DIR)

        try:
            with pd.ExcelWriter(cls.SAMPLE_FILE_PATH, engine='openpyxl') as writer:
                # Settings sheet with multiple tables
                settings_sheet_content_rows = [
                    ["~Scenario_Info", None, None], # Marker for Table 1
                    ["Parameter", "Value", "Unit"],
                    ["Scenario_Name", "Test_Scenario_2030", None],
                    ["Years", "2025,2030,2035", "CSV"],
                    ["CO2_Cap_Active", True, None],
                    [None, None, None], # Empty row to delimit table if no end marker
                    ["~CO2_Limits", None, None],    # Marker for Table 2
                    ["Year", "Limit_MT", "Notes"],
                    [2025, 10.5, "Interim Target"],
                    [2030, 8.0, "Final Target"],
                    [2035, 5.0, "Stretch Goal"],
                    # No explicit end marker, next table or end of sheet will delimit
                    [None, None, None],
                    ["~Solver_Options", None, None], # Marker for Table 3
                    ["Option", "Value", None],
                    ["SolverName", "gurobi", None],
                    ["MIPGap", 0.01, None]
                ]
                pd.DataFrame(settings_sheet_content_rows).to_excel(writer, sheet_name="Settings", index=False, header=False)

                # Component Sheet: Buses
                buses_data = {'name': ['Bus_A', 'Bus_B', 'Bus_C'],
                              'v_nom': [400, 220, 110],
                              'control_area': ['KSEB', 'KSEB', 'KSEB'],
                              'x': [10.0, 10.1, 10.2], 'y': [76.0, 76.1, 76.2]}
                pd.DataFrame(buses_data).to_excel(writer, sheet_name="Buses", index=False)

                # Component Sheet: Generators
                gens_data = {'name': ['Gen_Solar_A', 'Gen_Wind_B', 'Gen_Coal_C'],
                             'bus': ['Bus_A', 'Bus_B', 'Bus_C'],
                             'carrier': ['solar', 'wind', 'coal'],
                             'p_nom': [150, 100, 500],
                             'marginal_cost': [0, 0, 3.5]}
                pd.DataFrame(gens_data).to_excel(writer, sheet_name="Generators", index=False)

                # Time-Series Sheet: Demand (index is datetime, columns are bus names)
                demand_idx = pd.to_datetime(['2030-01-01 00:00', '2030-01-01 01:00', '2030-01-01 02:00'])
                demand_data = {'Bus_A': [100,110,105], 'Bus_B': [50,55,52], 'Bus_C': [200,210,205]}
                pd.DataFrame(demand_data, index=demand_idx).to_excel(writer, sheet_name="Demand") # Writes index

                # Cost/Parameter Sheet: Capital_cost
                cost_data = {'technology': ['solar_pv_utility', 'wind_onshore_turbine', 'coal_plant_new'],
                             'value': [600000, 1200000, 8000000],
                             'unit': ['INR/kW', 'INR/kW', 'INR/kW'],
                             'reference_year': [2023, 2023, 2023]}
                pd.DataFrame(cost_data).to_excel(writer, sheet_name="Capital_cost", index=False)

                # Custom sheet (should also be picked up)
                custom_sheet_data = {'info_col': ['extra_data_point1', 'extra_data_point2'], 'value_col': [101, 202]}
                pd.DataFrame(custom_sheet_data).to_excel(writer, sheet_name="My_Custom_Analysis_Data", index=False)

            print(f"Created sample file: {cls.SAMPLE_FILE_PATH}")
        except Exception as e:
            print(f"Error creating sample Excel file for PyPSA parser: {e}")
            raise

    @classmethod
    def tearDownClass(cls):
        """Remove the sample Excel file after tests."""
        if os.path.exists(cls.SAMPLE_FILE_PATH):
            os.remove(cls.SAMPLE_FILE_PATH)
            print(f"Removed sample file: {cls.SAMPLE_FILE_PATH}")
        try:
            if not os.listdir(cls.SAMPLE_FILE_DIR):
                 os.rmdir(cls.SAMPLE_FILE_DIR)
                 print(f"Removed directory: {cls.SAMPLE_FILE_DIR}")
        except OSError:
            print(f"Warning: Could not remove directory {cls.SAMPLE_FILE_DIR}.")

    def test_parse_pypsa_input_template_structure(self):
        """Test the basic structure of the parsed PyPSA data dictionary."""
        parsed_data = parse_pypsa_input_template(self.SAMPLE_FILE_PATH)
        self.assertIsNotNone(parsed_data, "Parser returned None, expected a dictionary.")

        # Check for presence of keys for sheets that were created
        expected_top_level_keys = ['Settings', 'Buses', 'Generators', 'Demand', 'Capital_cost', 'My_Custom_Analysis_Data']
        for key in expected_top_level_keys:
            self.assertIn(key, parsed_data, f"Key '{key}' (sheet/section name) missing in parsed data.")

        # Check that 'Settings' is a dictionary of DataFrames
        self.assertIsInstance(parsed_data.get('Settings'), dict, "'Settings' should be a dictionary of DataFrames.")

        # Check that other keys point to DataFrames
        for key in expected_top_level_keys:
            if key != 'Settings':
                self.assertIsInstance(parsed_data.get(key), pd.DataFrame, f"'{key}' should be a DataFrame.")

    def test_settings_sheet_parsing(self):
        """Test detailed parsing of the 'Settings' sheet with multiple tables."""
        parsed_data = parse_pypsa_input_template(self.SAMPLE_FILE_PATH)
        settings_tables = parsed_data.get('Settings')
        self.assertIsNotNone(settings_tables)

        expected_setting_tables = ['Scenario_Info', 'CO2_Limits', 'Solver_Options']
        for table_name in expected_setting_tables:
            self.assertIn(table_name, settings_tables, f"Table '~{table_name}' missing from Settings.")
            self.assertIsInstance(settings_tables[table_name], pd.DataFrame)
            self.assertFalse(settings_tables[table_name].empty, f"Table '~{table_name}' should not be empty.")

        # Check content of Scenario_Info table
        scenario_info_df = settings_tables['Scenario_Info']
        self.assertEqual(scenario_info_df[scenario_info_df['Parameter'] == 'Scenario_Name'].iloc[0]['Value'], 'Test_Scenario_2030')
        self.assertEqual(scenario_info_df[scenario_info_df['Parameter'] == 'Years'].iloc[0]['Value'], '2025,2030,2035')
        # Booleans should be preserved by openpyxl and pandas read if not explicitly converted to str by parser for data cells
        self.assertEqual(scenario_info_df[scenario_info_df['Parameter'] == 'CO2_Cap_Active'].iloc[0]['Value'], True)


        # Check content of CO2_Limits table
        co2_limits_df = settings_tables['CO2_Limits']
        self.assertListEqual(list(co2_limits_df.columns), ['Year', 'Limit_MT', 'Notes'])
        # Data in 'Year' and 'Limit_MT' columns should be numeric (float or int) as they are not headers
        self.assertEqual(co2_limits_df[co2_limits_df['Year'].astype(float) == 2030.0].iloc[0]['Limit_MT'], 8.0)

    def test_component_sheet_content(self):
        """Test content of a sample component sheet (e.g., 'Buses')."""
        parsed_data = parse_pypsa_input_template(self.SAMPLE_FILE_PATH)

        buses_df = parsed_data.get('Buses')
        self.assertIsNotNone(buses_df)
        self.assertFalse(buses_df.empty)
        self.assertIn('v_nom', buses_df.columns)
        self.assertEqual(buses_df[buses_df['name'] == 'Bus_A'].iloc[0]['v_nom'], 400)

        gens_df = parsed_data.get('Generators')
        self.assertIsNotNone(gens_df)
        self.assertFalse(gens_df.empty)
        self.assertIn('p_nom', gens_df.columns)
        self.assertEqual(gens_df[gens_df['name'] == 'Gen_Solar_A'].iloc[0]['p_nom'], 150)

    def test_timeseries_sheet_content(self):
        """Test content and index of a time-series sheet (e.g., 'Demand')."""
        parsed_data = parse_pypsa_input_template(self.SAMPLE_FILE_PATH)
        demand_df = parsed_data.get('Demand')
        self.assertIsNotNone(demand_df)
        self.assertFalse(demand_df.empty)
        self.assertIsInstance(demand_df.index, pd.DatetimeIndex, "Demand sheet index should be DatetimeIndex.")
        self.assertEqual(demand_df.loc['2030-01-01 00:00']['Bus_A'], 100)

    def test_missing_optional_sheet(self):
        """Test that a missing optional sheet results in an empty DataFrame."""
        parsed_data = parse_pypsa_input_template(self.SAMPLE_FILE_PATH)
        # 'Links' is in component_sheets list but not in the sample file
        self.assertIn('Links', parsed_data, "Key for optional missing sheet 'Links' should exist.")
        self.assertTrue(parsed_data['Links'].empty, "Data for missing optional sheet 'Links' should be an empty DataFrame.")

    def test_custom_sheet_loading(self):
        """Test that custom sheets not in predefined lists are also loaded."""
        parsed_data = parse_pypsa_input_template(self.SAMPLE_FILE_PATH)
        self.assertIn('My_Custom_Analysis_Data', parsed_data)
        custom_df = parsed_data['My_Custom_Analysis_Data']
        self.assertIsInstance(custom_df, pd.DataFrame)
        self.assertFalse(custom_df.empty)
        self.assertIn('info_col', custom_df.columns)
        self.assertEqual(custom_df.iloc[0]['value_col'], 101)

if __name__ == '__main__':
    unittest.main()
